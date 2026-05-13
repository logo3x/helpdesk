<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\KbEmbedding;
use Illuminate\Support\Collection;

/**
 * Retrieval Augmented Generation.
 *
 * Primary mode: cosine similarity on vector embeddings (requires
 * IndexKbArticleJob to have run + LLM with embeddings API).
 *
 * Fallback mode: keyword overlap search on KbArticle title+body.
 * Used when no embeddings exist yet or the LLM provider does not
 * support the /embeddings endpoint (e.g. most OpenRouter free tier).
 */
class RagService
{
    public function __construct(
        protected LlmService $llm,
    ) {}

    /**
     * Search for the top-N most relevant KB chunks for a query.
     * Falls back to keyword search if vector embeddings are unavailable.
     *
     * @return Collection<int, array{content: string, similarity: float, article_id: int, article_title: string|null}>
     */
    public function search(string $query, int $topN = 3, float $threshold = 0.4): Collection
    {
        // Prefer vector search when embeddings exist for at least one article
        if (KbEmbedding::query()->exists()) {
            $vectorResults = $this->vectorSearch($query, $topN, $threshold);

            if ($vectorResults->isNotEmpty()) {
                return $vectorResults;
            }
        }

        return $this->keywordSearch($query, $topN);
    }

    /**
     * Build a context string from the top search results for the LLM prompt.
     */
    public function buildContext(string $query, int $topN = 3): string
    {
        $results = $this->search($query, $topN);

        if ($results->isEmpty()) {
            return '';
        }

        return $results->map(fn (array $item) => "[Artículo: {$item['article_title']}]\n{$item['content']}")
            ->implode("\n\n---\n\n");
    }

    /**
     * Cosine similarity vector search.
     *
     * @return Collection<int, array{content: string, similarity: float, article_id: int, article_title: string|null}>
     */
    protected function vectorSearch(string $query, int $topN, float $threshold): Collection
    {
        $queryVector = $this->llm->embed($query);

        if ($queryVector === null) {
            return collect();
        }

        // Chunk para no cargar miles de embeddings en memoria. Cada chunk
        // calcula similitud y acumula los top-N. Escalable a decenas de
        // miles de embeddings sin sobrecargar la RAM.
        $heap = collect();

        KbEmbedding::with('article:id,title,slug')
            ->chunk(500, function ($embeddings) use (&$heap, $queryVector, $threshold, $topN): void {
                foreach ($embeddings as $emb) {
                    $sim = $emb->cosineSimilarity($queryVector);

                    if ($sim < $threshold) {
                        continue;
                    }

                    $heap->push([
                        'content' => $emb->content,
                        'similarity' => $sim,
                        'article_id' => $emb->kb_article_id,
                        'article_title' => $emb->article?->title,
                    ]);

                    // Mantener solo los top-N en memoria mientras iteramos.
                    if ($heap->count() > $topN * 4) {
                        $heap = $heap->sortByDesc('similarity')->take($topN * 2)->values();
                    }
                }
            });

        return $heap->sortByDesc('similarity')->take($topN)->values();
    }

    /**
     * Keyword overlap search con stemming en español + soporte de plurales
     * y conjugaciones verbales. Es lo "suficientemente bueno" para <100
     * artículos sin necesitar embeddings reales.
     *
     * Mejora vs versión anterior: hace stem de la query y del cuerpo del
     * artículo, así "instalo" matchea con "instalar", "contraseñas" con
     * "contraseña", "configurando" con "configurar", etc. Sin esto, el
     * RAG fallaba ante lenguaje natural ("hola como instalo teams en mi
     * pc" no encontraba el artículo "Cómo instalar Microsoft Teams").
     *
     * @return Collection<int, array{content: string, similarity: float, article_id: int, article_title: string|null}>
     */
    protected function keywordSearch(string $query, int $topN): Collection
    {
        $queryStems = $this->tokenizeAndStem($query);

        if ($queryStems->isEmpty()) {
            return collect();
        }

        $articles = KbArticle::query()->published()->get(['id', 'title', 'body']);

        return $articles
            ->map(function (KbArticle $article) use ($queryStems) {
                $haystackStems = $this->tokenizeAndStem(
                    $article->title.' '.$article->body,
                    keepStopwords: true,
                );

                // Set de stems del haystack para matching O(1).
                $haystackSet = $haystackStems->flip();

                // Cada stem del query cuenta si está en el haystack.
                // El título pesa doble (palabras del título son señal fuerte
                // de relevancia) para que un match en el title supere a un
                // simple match en el body.
                $titleStems = $this->tokenizeAndStem($article->title, keepStopwords: true)->flip();

                $score = 0.0;
                foreach ($queryStems as $stem) {
                    if ($titleStems->has($stem)) {
                        $score += 1.5;
                    } elseif ($haystackSet->has($stem)) {
                        $score += 1.0;
                    }
                }

                $similarity = $queryStems->count() > 0 ? $score / $queryStems->count() : 0.0;

                return [
                    'content' => mb_substr($article->body, 0, 1500),
                    'similarity' => round(min($similarity, 1.0), 4),
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                ];
            })
            ->filter(fn (array $item) => $item['similarity'] > 0)
            ->sortByDesc('similarity')
            ->take($topN)
            ->values();
    }

    /**
     * Tokeniza un texto y devuelve los stems únicos relevantes.
     * Quita stopwords (a menos que `keepStopwords` esté en true para
     * indexar el haystack completo) y palabras < 2 caracteres.
     *
     * @return Collection<int, string>
     */
    protected function tokenizeAndStem(string $text, bool $keepStopwords = false): Collection
    {
        // Stopwords mínimas — solo palabras que NUNCA aportan al matching
        // de un helpdesk en español. No incluimos verbos como "necesito",
        // "puedo", "quiero" porque su stem ("necesit", "ayud") puede
        // matchear en artículos relevantes ("ayuda con VPN").
        $stopwords = [
            'el', 'la', 'lo', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'de', 'del', 'al', 'a', 'en', 'y', 'o', 'u', 'e', 'que', 'qu',
            'por', 'para', 'con', 'sin', 'sobre', 'entre', 'hasta', 'desde',
            'mi', 'mis', 'tu', 'tus', 'su', 'sus', 'me', 'te', 'se', 'nos',
            'es', 'son', 'fue', 'soy', 'eres', 'somos', 'sois',
            'ha', 'he', 'has', 'hemos', 'han', 'hay',
            'esto', 'esta', 'estos', 'estas', 'eso', 'esa', 'esos', 'esas',
            'aqui', 'alli', 'aca', 'alla',
            'hola', 'buenas', 'gracias', 'favor',
        ];

        $normalized = $this->stripAccentsAndPunctuation(mb_strtolower($text));

        return collect(preg_split('/\s+/', $normalized))
            ->filter(fn ($t) => mb_strlen($t) >= 2)
            ->reject(fn ($t) => ! $keepStopwords && in_array($t, $stopwords, true))
            ->map(fn ($t) => $this->stem($t))
            ->filter(fn ($t) => mb_strlen($t) >= 2)
            ->unique()
            ->values();
    }

    /**
     * Stemmer ligero para español inspirado en Snowball-es.
     *
     * Aplica tres pasos en orden, parando cuando la raíz queda corta
     * para no caer en stems ambiguos ("la" → ""):
     *   1. Quita sufijos verbales / nominales largos (-amiento, -iendo,
     *      -ando, -ar, -er, -ir, -ado, -aron, -amos, etc.).
     *   2. Quita la `s` final del plural si quedan ≥ 4 chars.
     *   3. Quita la vocal final (-a/-e/-i/-o/-u) si quedan ≥ 4 chars.
     *
     * Esto garantiza que tanto "contraseña" como "contraseñas" terminan
     * en "contraseñ", y "instalar/instalo/instalando/instalaron" todos
     * en "instal". El matching del RAG necesita simetría: un mismo stem
     * para la query y el haystack.
     */
    protected function stem(string $word): string
    {
        if (mb_strlen($word) <= 3) {
            return $word;
        }

        $longSuffixes = [
            // Gerundios e infinitivos compuestos
            'amiento', 'imiento',
            'iendo', 'ando',
            // Pretéritos comunes
            'aron', 'ieron', 'aban', 'ian',
            'aste', 'iste',
            // Infinitivos reflexivos
            'arse', 'erse', 'irse',
            // Conjugaciones primera persona plural
            'amos', 'emos', 'imos',
            // Condicional
            'aria', 'eria', 'iria',
            // Participios masculino/femenino + plurales
            'adas', 'idas', 'ados', 'idos',
            'ada', 'ida', 'ado', 'ido',
            // Imperfectos
            'aba',
            // Plurales de palabras terminadas en consonante (-es)
            'es',
            // Conjugaciones / plurales cortos
            'as', 'os',
            // Infinitivos
            'ar', 'er', 'ir',
            // Imperfecto -ía
            'ia',
        ];

        // Paso 1: sufijo verbal/nominal largo.
        foreach ($longSuffixes as $suffix) {
            $suffixLen = mb_strlen($suffix);
            $wordLen = mb_strlen($word);

            if ($wordLen - $suffixLen < 3) {
                continue;
            }

            if (str_ends_with($word, $suffix)) {
                $word = mb_substr($word, 0, $wordLen - $suffixLen);
                break;
            }
        }

        // Paso 2: `s` final (plural simple).
        if (mb_strlen($word) >= 5 && str_ends_with($word, 's')) {
            $word = mb_substr($word, 0, mb_strlen($word) - 1);
        }

        // Paso 3: vocal final (singular masculino/femenino, presente 3ª pers).
        if (mb_strlen($word) >= 4 && preg_match('/[aeiou]$/u', $word)) {
            $word = mb_substr($word, 0, mb_strlen($word) - 1);
        }

        return $word;
    }

    /**
     * Normaliza acentos y quita puntuación para matching robusto:
     *   "Cómo configurar tu cuenta?" → "como configurar tu cuenta"
     */
    protected function stripAccentsAndPunctuation(string $text): string
    {
        $accents = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u',
        ];
        $text = strtr($text, $accents);

        return preg_replace('/[¿?¡!.,;:()\[\]{}"\'\/\\\\\-_]+/', ' ', $text);
    }
}
