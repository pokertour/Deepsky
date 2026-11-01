# DeepSky / NightScope — récapitulatif et passation

> Mis à jour le 21 octobre 2026 (files d’attente, distances, issue #4).

## 1. Contexte important

DeepSky est une application Laravel 13 / PHP 8.5 (tests PHPUnit). NightScope est intégré **dans cette application**. `SetupItem` reste la source de vérité matériel ; `EquipmentSetup` = Rig réutilisable.

Admin Blade/Tailwind/Alpine (Filament retiré), navigation Livewire. **Ne pas** lancer de restauration globale destructive.

## 2. État de validation

- Migrations appliquées jusqu’à `distance_checked_at` (celestial_objects).
- Suite complète : **352** tests verts.
- Cache Tonight : **v19** ; cache météo : **v2** ; filtres galerie : `gallery.filter-options.v1`.

## 3. Réalisé (dont reprise)

### Admin Blade + NightScope core
- Admin `/admin` (photos, équipements, Rigs, lieux, catalogue, newsletter, settings).
- Menu NightScope : Tonight, Forecast 7 nuits, Planner, Catalogue.

### Target Detail (P3)
- Courbe Lune + cible, bande fenêtre ≥ 30°, marqueur transit.
- Captures historiques avec liens admin.
- Sélecteurs date / lieu / Rig.
- Code catalogue + nom dans les infos cible.

### Scores (P4)
- `InterestScoreCalculator` : intérêt photographique (notoriété Messier/Caldwell/nom/LBN, éclat, taille) ; poids `scores.target.interest`, bonus « jamais photographié » proportionnel à l'intérêt ; altitude saturée à `scores.altitude_full_score_deg` (60°).
- Présélection Tonight : objets réellement au-dessus de `tonight.candidate_min_altitude` pendant la nuit, classés par intérêt (les nébuleuses sans magnitude ne sont plus écartées).
- Sans nuit astronomique (été au nord de 48,6°), Tonight et Target Detail utilisent la nuit nautique.
- `EquipmentScoreCalculator` (focale, sampling, filtres, champ).
- `HistoricalScoreCalculator` (récence, intégration, diversité).
- Pondérations documentées dans `config/nightscope.php`.

### Météo
- Open-Meteo = base **7 jours**.
- **7Timer! astro** = seeing + transparency sur ~**3 premiers jours**.
- `CompositeWeatherProvider` fusionne les deux ; dégradation si 7Timer échoue.
- NightScore / TargetScore consomment le WeatherScore enrichi.
- Affichage Tonight + Forecast.

### Planner (P5 partiel)
- Algo : altitude ≥ 30°, horizon, transit, séparation lunaire, densité score×durée.
- Plans : notes, delete, duplicate date future, reorder, remove target.
- **Ajout manuel** de cible (recherche catalogue + horaires optionnels).
- Session : snapshot météo au start, notes éditables, photos liées.
- **Acquisition** : gain + sous-pose conseillés (préférence caméra → historique Rig → heuristique f/ratio + filtre).

### Catalogue (P6)
- Import **IC** + Messier/NGC ; **addendum** OpenNGC (soft-fail).
- Aliases fusionnés : Caldwell (`C 20`), Sharpless (`Sh2-…`), croisés NGC/IC.
- Recherche admin enrichie : type, constellation, mag, taille, photographié/non.
- `php artisan nightscope:import-catalog --latest`
- **Distances** via SIMBAD TAP (`nightscope:enrich-distances`) + bouton Target Detail.
  - Passage **quotidien** (03:40, 150 objets), objets photographiés en premier.
  - `distance_checked_at` : un objet introuvable n’est réessayé qu’après 30 jours (`recheck_days`).
  - `EnrichCelestialDistanceJob` dès qu’une photo est liée à un objet sans distance.
- **Taille réelle** (al) calculée depuis distance + grand axe angulaire, affichée sur les pages photo (#4).

### Médias et files d’attente
- Toutes les conversions (`thumb`, `optimized`, `full_res`) passent par la file `media` ; l’upload ne fait que stocker.
- `PerformAstrophotoConversionsJob` : timeout 900 s, `failOnTimeout` ; `retry_after` 960 s.
- Supervisor : `queue-worker` (file `default`, 120 s) et `media-worker` (file `media`, 900 s).
- Statuts de conversion : enum `ConversionStatus`.

## 4. Données locales

- Région lieu : **Loire-Atlantique** (déjà renseignée).
- Pas de rattachement auto photos → lieu/Rig (manuel utilisateur).
- Specs Canon 60D / Rig DSLR-Old : toujours à compléter si besoin.

## 5. Il reste à faire

### Priorité 1 — stabilisation
- QA visuelle desktop/mobile authentifiée (pass mobile NightScope amorcée : sous-nav, header, grilles).
- DSS2 lent/indisponible ; cadrage multi-Rigs.
- Deploy prod : scheduler, workers `default` + `media`, Open-Meteo + 7Timer + CDS + GitHub.
- Après déploiement : relancer depuis l’admin les conversions restées sur l’ancienne file `default`.

### Priorité 2 — données utilisateur
- Relier anciennes photos à lieu / Rig / objet céleste.
- Specs Canon 60D.

### Priorité 5 — planner avancé (reste)
- Optimisation globale (OR), météo horaire dans l’algo, méridien, priorités manuelles, temps changement filtre/Rig.

### Priorité 6 — catalogue (reste)
- Couverture distances partielle (objets absents de SIMBAD / sans mesure) ; rattrapage complet du catalogue en ~2 mois.

### Divers
- Format `full_res` (PNG lourd sur R2) à arbitrer : WebP sans perte ou JPEG.
- Likes (cookie) et compteur de visites faciles à gonfler.

### Hors scope
- NativePHP, multi-tenant. (PWA hors connexion et mode nuit rouge : livrés.)

## 6. Points techniques

- Ne jamais exposer `ObservingLocation.name|lat|lon` ni `location_tag` en public.
- Preview DSS2 : chargement navigateur, fallback UI.
- Incrémenter version cache Tonight/météo si DTO changent.
- `NIGHTSCOPE_WEATHER_DRIVER=open_meteo`
- `NIGHTSCOPE_SEVEN_TIMER_ENABLED=true` (défaut)

## 7. Commandes utiles

```bash
php artisan test --compact
php artisan nightscope:import-catalog --latest
php artisan nightscope:enrich-distances --limit=150
php artisan nightscope:warm-weather
php artisan schedule:list
npm run build
```
