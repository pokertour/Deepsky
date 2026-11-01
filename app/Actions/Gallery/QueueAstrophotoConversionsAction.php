<?php

namespace App\Actions\Gallery;

use App\Enums\ConversionStatus;
use App\Models\Astrophoto;
use DomainException;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\Conversions\ImageGenerators\ImageGeneratorFactory;

class QueueAstrophotoConversionsAction
{
    public function __construct(private FileManipulator $fileManipulator) {}

    /**
     * Remet en file les conversions manquantes de l'image principale et de la
     * version sans étoiles ; celles déjà générées ne sont pas refaites.
     *
     * @throws DomainException quand rien ne peut être converti
     */
    public function execute(Astrophoto $astrophoto): void
    {
        $media = $astrophoto->getMedia('astros')->merge($astrophoto->getMedia('starless'));

        if ($media->isEmpty()) {
            throw new DomainException(__('Ajoute une image avant de relancer les conversions.'));
        }

        foreach ($media as $item) {
            if (ImageGeneratorFactory::forMedia($item) === null) {
                $item->setCustomProperty('processing_status', ConversionStatus::Failed->value)->save();

                throw new DomainException(__('Ce format ne peut pas être converti par le serveur. Vérifie Imagick ou remplace l’image.'));
            }

            // touch() date la remise en file même si le statut était déjà « queued » :
            // sans cela, le rattrapage horaire relancerait la même conversion à chaque passage.
            $item->setCustomProperty('processing_status', ConversionStatus::Queued->value)->touch();
            $this->fileManipulator->createDerivedFiles($item, onlyMissing: true, queueAll: true);
        }
    }
}
