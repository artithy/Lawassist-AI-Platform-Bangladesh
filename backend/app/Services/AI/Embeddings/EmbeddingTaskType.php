<?php

namespace App\Services\AI\Embeddings;

/**
 * The task types Google documents for gemini-embedding-001 (the "taskType" field of an embed request). The type tells the
 * model what the vector will be used for. For search, texts that are stored use RetrievalDocument and the user's question
 * uses RetrievalQuery; both sides must use the same model and the same number of dimensions.
 */
enum EmbeddingTaskType: string
{
    case SemanticSimilarity = 'SEMANTIC_SIMILARITY';
    case Classification = 'CLASSIFICATION';
    case Clustering = 'CLUSTERING';
    case RetrievalDocument = 'RETRIEVAL_DOCUMENT';
    case RetrievalQuery = 'RETRIEVAL_QUERY';
    case CodeRetrievalQuery = 'CODE_RETRIEVAL_QUERY';
    case QuestionAnswering = 'QUESTION_ANSWERING';
    case FactVerification = 'FACT_VERIFICATION';
}
