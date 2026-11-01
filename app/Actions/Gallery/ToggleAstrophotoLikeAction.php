<?php

namespace App\Actions\Gallery;

use App\Models\Astrophoto;
use Illuminate\Support\Facades\DB;

class ToggleAstrophotoLikeAction
{
    /**
     * Le cookie reflète ce que voit le visiteur ; la table astrophoto_likes
     * empêche qu'un cookie effacé permette de liker plusieurs fois.
     * Un cookie « aimé » sans ligne en base correspond aux likes antérieurs
     * à cette table : il reste annulable.
     *
     * @return bool true si la photo est aimée après l'appel
     */
    public function execute(Astrophoto $photo, string $visitorHash, bool $likedInCookie): bool
    {
        return DB::transaction(function () use ($photo, $visitorHash, $likedInCookie): bool {
            $likes = DB::table('astrophoto_likes')->where('astrophoto_id', $photo->id)->where('visitor_hash', $visitorHash);

            if ($likedInCookie) {
                $likes->delete();

                if ($photo->likes_count > 0) {
                    $photo->decrement('likes_count');
                }

                return false;
            }

            $inserted = DB::table('astrophoto_likes')->insertOrIgnore([
                'astrophoto_id' => $photo->id,
                'visitor_hash' => $visitorHash,
                'created_at' => now(),
            ]);

            if ($inserted === 1) {
                $photo->increment('likes_count');
            }

            return true;
        });
    }
}
