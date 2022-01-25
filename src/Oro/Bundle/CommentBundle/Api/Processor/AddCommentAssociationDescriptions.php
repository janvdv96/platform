<?php

namespace Oro\Bundle\CommentBundle\Api\Processor;

use Oro\Bundle\ApiBundle\ApiDoc\EntityDescriptionProvider;
use Oro\Bundle\ApiBundle\ApiDoc\ResourceDocParserInterface;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\ResourceDocParserProvider;
use Oro\Bundle\ApiBundle\Processor\GetConfig\ConfigContext;
use Oro\Bundle\ApiBundle\Request\ApiAction;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Util\ValueNormalizerUtil;
use Oro\Bundle\CommentBundle\Api\CommentAssociationProvider;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Adds human-readable descriptions for associations with the comment entity.
 */
class AddCommentAssociationDescriptions implements ProcessorInterface
{
    private const COMMENTS_ASSOCIATION_NAME = 'comments';

    private const COMMENT_ASSOCIATION_DOC_RESOURCE = '@OroCommentBundle/Resources/doc/api/comment_association.md';
    private const COMMENT_TARGET_ENTITY = '%comment_target_entity%';
    private const COMMENTS_ASSOCIATION = '%comments_association%';

    private CommentAssociationProvider $commentAssociationProvider;
    private ResourceDocParserProvider $resourceDocParserProvider;
    private EntityDescriptionProvider $entityDescriptionProvider;
    private ValueNormalizer $valueNormalizer;

    public function __construct(
        CommentAssociationProvider $commentAssociationProvider,
        ResourceDocParserProvider $resourceDocParserProvider,
        EntityDescriptionProvider $entityDescriptionProvider,
        ValueNormalizer $valueNormalizer
    ) {
        $this->commentAssociationProvider = $commentAssociationProvider;
        $this->resourceDocParserProvider = $resourceDocParserProvider;
        $this->entityDescriptionProvider = $entityDescriptionProvider;
        $this->valueNormalizer = $valueNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var ConfigContext $context */

        $targetAction = $context->getTargetAction();
        if (!$targetAction || ApiAction::OPTIONS === $targetAction) {
            return;
        }

        $associationName = $context->getAssociationName();
        $entityClass = $associationName ? $context->getParentClassName() : $context->getClassName();
        $version = $context->getVersion();
        $requestType = $context->getRequestType();

        $commentAssociationName = $this->commentAssociationProvider->getCommentAssociationName(
            $entityClass,
            $version,
            $requestType
        );
        if ($commentAssociationName) {
            $this->addCommentAssociationDescriptions(
                $context->getResult(),
                $requestType,
                $targetAction,
                $entityClass,
                $associationName
            );
        }
    }

    private function addCommentAssociationDescriptions(
        EntityDefinitionConfig $definition,
        RequestType $requestType,
        string $targetAction,
        string $entityClass,
        ?string $associationName
    ): void {
        if (!$associationName) {
            $this->setDescriptionsForCommentsField($definition, $requestType, $entityClass, $targetAction);
        } elseif (self::COMMENTS_ASSOCIATION_NAME === $associationName && !$definition->hasDocumentation()) {
            $this->setDescriptionsForSubresource(
                $definition,
                $requestType,
                $entityClass,
                $targetAction
            );
        }
    }

    private function setDescriptionsForCommentsField(
        EntityDefinitionConfig $definition,
        RequestType $requestType,
        string $entityClass,
        string $targetAction
    ): void {
        $commentsAssociationDefinition = $definition->getField(self::COMMENTS_ASSOCIATION_NAME);
        if (null === $commentsAssociationDefinition) {
            return;
        }
        if ($commentsAssociationDefinition->hasDescription()) {
            return;
        }

        $docParser = $this->getDocumentationParser($requestType, self::COMMENT_ASSOCIATION_DOC_RESOURCE);
        $associationDocumentationTemplate = $docParser->getFieldDocumentation(
            self::COMMENT_TARGET_ENTITY,
            self::COMMENTS_ASSOCIATION,
            $targetAction
        );
        if (!$associationDocumentationTemplate) {
            $associationDocumentationTemplate = $docParser->getFieldDocumentation(
                self::COMMENT_TARGET_ENTITY,
                self::COMMENTS_ASSOCIATION
            );
        }

        $commentsAssociationDefinition->setDescription(strtr($associationDocumentationTemplate, [
            '%entity_name%' => $this->getEntityName($entityClass, $requestType)
        ]));
    }

    private function setDescriptionsForSubresource(
        EntityDefinitionConfig $definition,
        RequestType $requestType,
        string $entityClass,
        string $targetAction
    ): void {
        $docParser = $this->getDocumentationParser($requestType, self::COMMENT_ASSOCIATION_DOC_RESOURCE);
        $subresourceDocumentationTemplate = $docParser->getSubresourceDocumentation(
            self::COMMENT_TARGET_ENTITY,
            self::COMMENTS_ASSOCIATION,
            $targetAction
        );

        $definition->setDocumentation(strtr($subresourceDocumentationTemplate, [
            '%entity_name%' => $this->getEntityName($entityClass, $requestType)
        ]));
    }

    private function getDocumentationParser(
        RequestType $requestType,
        string $documentationResource
    ): ResourceDocParserInterface {
        $docParser = $this->resourceDocParserProvider->getResourceDocParser($requestType);
        $docParser->registerDocumentationResource($documentationResource);

        return $docParser;
    }

    private function getEntityType(string $entityClass, RequestType $requestType): string
    {
        return ValueNormalizerUtil::convertToEntityType($this->valueNormalizer, $entityClass, $requestType);
    }

    private function getEntityName(string $entityClass, RequestType $requestType): string
    {
        $result = $this->entityDescriptionProvider->getEntityDescription($entityClass);
        if (!$result) {
            return $this->getEntityType($entityClass, $requestType);
        }

        return strtolower($result);
    }
}