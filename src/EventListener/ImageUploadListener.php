<?php

namespace App\EventListener;

use App\Service\ImageOptimizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events as VichUploaderEvents;

class ImageUploadListener implements EventSubscriberInterface
{
    public function __construct(
        private ImageOptimizer $imageOptimizer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VichUploaderEvents::POST_UPLOAD => 'onPostUpload',
        ];
    }

    public function onPostUpload(Event $event): void
    {
        $mapping = $event->getMapping();
        $object = $event->getObject();

        $filePath = $mapping->getUploadDestination() . '/' . $mapping->getFileName($object);

        if (file_exists($filePath)) {
            $this->imageOptimizer->optimize($filePath);
        }
    }
}