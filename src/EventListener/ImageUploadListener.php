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
            VichUploaderEvents::PRE_UPLOAD => 'onPreUpload',
        ];
    }

    public function onPreUpload(Event $event): void
    {
        $mapping = $event->getMapping();
        $object = $event->getObject();

        // Get the uploaded file
        $file = $mapping->getFile($object);

        if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && file_exists($file->getPathname())) {
            $this->imageOptimizer->optimize($file->getPathname());
        }
    }
}