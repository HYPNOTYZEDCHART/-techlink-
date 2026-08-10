<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use App\Entity\CustomerOrder;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::onFlush)]
class ActivityLogListener
{
    private const TRACKED_ENTITIES = [Product::class, CustomerOrder::class];

    public function __construct(
        private Security $security,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $user = $this->security->getUser();

        if (!$user) {
            return;
        }

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->log($em, $entity, 'created', $user);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->log($em, $entity, 'updated', $user);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->log($em, $entity, 'deleted', $user);
        }
    }

    private function log(\Doctrine\ORM\EntityManagerInterface $em, object $entity, string $action, \App\Entity\User $user): void
    {
        if (!in_array(get_class($entity), self::TRACKED_ENTITIES, true)) {
            return;
        }

        $log = new ActivityLog();
        $log->setAction($action);
        $log->setEntityType((new \ReflectionClass($entity))->getShortName());
        $log->setEntityId($entity->getId() ?? 0);
        $log->setUser($user);
        $log->setCreatedAt(new \DateTimeImmutable());

        $em->persist($log);
        $em->getUnitOfWork()->computeChangeSet($em->getClassMetadata(ActivityLog::class), $log);
    }
}