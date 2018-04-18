<?php
namespace Neos\Flow\Persistence\Doctrine;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\ORM\Event\OnFlushEventArgs;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\ObjectValidationFailedException;
use Neos\Flow\Reflection\ClassSchema;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Flow\Validation\ValidatorResolver;
use Neos\Utility\ObjectAccess;
use Neos\Utility\TypeHandling;

/**
 * An onFlush listener for Flow's Doctrine PersistenceManager.
 *
 * Used to de-duplicate value objects and validate new and updated objects during flush.
 *
 * @Flow\Scope("singleton")
 * @api
 */
class ObjectValidationAndDeDuplicationListener
{
    /**
     * @Flow\Inject
     * @var ValidatorResolver
     */
    protected $validatorResolver;

    /**
     * @Flow\Inject
     * @var ReflectionService
     */
    protected $reflectionService;

    /**
     * @Flow\Inject
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * An onFlush event listener used to act upon persistence.
     *
     * @param OnFlushEventArgs $eventArgs
     * @return void
     * @throws ObjectValidationFailedException
     */
    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        $unitOfWork = $eventArgs->getEntityManager()->getUnitOfWork();
        $validatedInstancesContainer = new \SplObjectStorage();

        $this->deduplicateValueObjectInsertions($unitOfWork, $validatedInstancesContainer);

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->validateObject($entity, $validatedInstancesContainer);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->validateObject($entity, $validatedInstancesContainer);
        }
    }

    /**
     * Loops over scheduled insertions and checks for duplicate value objects. Any duplicates are unset from the
     * list of scheduled insertions.
     *
     * @param UnitOfWork $unitOfWork
     * @param \SplObjectStorage $validatedInstancesContainer
     * @return void
     */
    private function deduplicateValueObjectInsertions(UnitOfWork $unitOfWork, \SplObjectStorage &$validatedInstancesContainer)
    {
        $entityInsertions = $unitOfWork->getScheduledEntityInsertions();

        $knownValueObjects = [];
        foreach ($entityInsertions as $entity) {
            $className = TypeHandling::getTypeForValue($entity);
            if ($this->reflectionService->getClassSchema($className)->getModelType() === ClassSchema::MODELTYPE_VALUEOBJECT) {
                $identifier = $this->persistenceManager->getIdentifierByObject($entity);

                if (isset($knownValueObjects[$className][$identifier]) || $unitOfWork->getEntityPersister($className)->exists($entity)) {
                    unset($entityInsertions[spl_object_hash($entity)]);
                    continue;
                }

                $knownValueObjects[$className][$identifier] = true;
            }
        }

        ObjectAccess::setProperty($unitOfWork, 'entityInsertions', $entityInsertions, true);
    }

    /**
     * Validates the given object and throws an exception if validation fails.
     *
     * @param object $object
     * @param \SplObjectStorage $validatedInstancesContainer
     * @return void
     * @throws ObjectValidationFailedException
     */
    private function validateObject($object, \SplObjectStorage $validatedInstancesContainer)
    {
        $className = $this->entityManager->getClassMetadata(get_class($object))->getName();
        $validator = $this->validatorResolver->getBaseValidatorConjunction($className, ['Persistence', 'Default']);
        if ($validator === null) {
            return;
        }

        $validator->setValidatedInstancesContainer($validatedInstancesContainer);
        $validationResult = $validator->validate($object);
        if ($validationResult->hasErrors()) {
            $errorMessages = '';
            $errorCount = 0;
            $allErrors = $validationResult->getFlattenedErrors();
            foreach ($allErrors as $path => $errors) {
                $errorMessages .= $path . ':' . PHP_EOL;
                foreach ($errors as $error) {
                    $errorCount++;
                    $errorMessages .= (string)$error . PHP_EOL;
                }
            }
            throw new ObjectValidationFailedException('An instance of "' . get_class($object) . '" failed to pass validation with ' . $errorCount . ' error(s): ' . PHP_EOL . $errorMessages, 1322585164);
        }
    }
}
