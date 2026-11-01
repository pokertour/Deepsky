<?php

namespace App\Console\Commands;

use App\Enums\ConversionStatus;
use App\Models\Astrophoto;
use Illuminate\Console\Command;

class PublishScheduledAstrophotos extends Command
{
    protected $signature = 'astrophotos:publish-scheduled';

    protected $description = 'Publie les brouillons programmés dont l\'heure est passée et dont les images sont prêtes (la newsletter part à la publication).';

    public function handle(): int
    {
        $published = 0;
        $waiting = 0;

        foreach (Astrophoto::query()->dueForPublication()->with('media')->get() as $photo) {
            // Une photo programmée avant la fin des conversions attend le passage suivant.
            if ($photo->conversion_status !== ConversionStatus::Ready) {
                $waiting++;

                continue;
            }

            $photo->update(['is_published' => true, 'publish_at' => null]);
            $published++;
        }

        $this->components->info("{$published} photo(s) publiée(s), {$waiting} en attente des conversions.");

        return self::SUCCESS;
    }
}
