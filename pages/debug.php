<?php
/**
 * debug_professionnels.php
 * ─────────────────────────────────────────────────────────────
 * Fichier de diagnostic AUTONOME — ne modifie rien
 * Placer à la racine du projet, ex : /debug_professionnels.php
 * SUPPRIMER après vérification !
 * ─────────────────────────────────────────────────────────────
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ── Connexion ──────────────────────────────────────────────
$pdo    = null;
$dbErr  = null;
try {
    require_once __DIR__ . '/config/database.php';
    $pdo = getPDO();
} catch (Throwable $e) {
    $dbErr = $e->getMessage();
}

// ── Helpers ────────────────────────────────────────────────
function detectCat(string $f): string {
    $fn = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $f));
    if (strpos($fn, 'delegu')       !== false) return 'delegue';
    if (strpos($fn, 'depositaire')  !== false
     || strpos($fn, 'responsable dep') !== false) return 'depositaire';
    if (strpos($fn, 'pharmacien')   !== false) return 'pharmacien';
    return 'autre';
}
function q(PDO $pdo, string $sql, array $p = []): array {
    $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC);
}
function val(PDO $pdo, string $sql, array $p = []) {
    $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchColumn();
}
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function badge(bool $ok, string $yes='OK', string $no='ERREUR'): string {
    return '<span class="badge '.($ok?'ok':'err').'">'.($ok?$yes:$no).'</span>';
}
function ico(bool $ok): string {
    return $ok
        ? '<svg class="ico ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>'
        : '<svg class="ico err" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
}

// ── Collecte des données ──────────────────────────────────
$tables       = [];
$rows         = [];
$joinRows     = [];
$catStats     = [];
$joinErr      = null;
$totalActifs  = 0;

if ($pdo) {
    try { $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}

    if (in_array('professionnels_dpm', $tables)) {
        $totalActifs = (int) val($pdo, "SELECT COUNT(*) FROM professionnels_dpm WHERE deleted_at IS NULL");

        // Requête simple — 10 premiers
        $rows = q($pdo, "SELECT id, prenom, nom, sexe, fonction, agence_id, pharmacie_id, depot_id, date_validite, is_active
                          FROM professionnels_dpm WHERE deleted_at IS NULL ORDER BY nom, prenom LIMIT 10");

        // Requête avec JOIN (exactement comme professionnels.php)
        $hasA  = in_array('agences_dpm',    $tables);
        $hasPh = in_array('pharmacies_dpm', $tables);
        $hasDp = in_array('depots_dpm',     $tables);

        $sA  = $hasA  ? ', a.nom AS agence_nom'               : ', NULL AS agence_nom';
        $sPh = $hasPh ? ', ph.nom_pharmacie AS pharmacie_nom'  : ', NULL AS pharmacie_nom';
        $sDp = $hasDp ? ', dep.nom AS depot_nom'               : ', NULL AS depot_nom';
        $jA  = $hasA  ? ' LEFT JOIN agences_dpm    a   ON a.id=p.agence_id'       : '';
        $jPh = $hasPh ? ' LEFT JOIN pharmacies_dpm ph  ON ph.id=p.pharmacie_id'   : '';
        $jDp = $hasDp ? ' LEFT JOIN depots_dpm     dep ON dep.id=p.depot_id'      : '';

        $sql = "SELECT p.id, p.prenom, p.nom, p.sexe, p.fonction,
                       p.agence_id, p.pharmacie_id, p.depot_id
                       $sA $sPh $sDp
                FROM professionnels_dpm p $jA $jPh $jDp
                WHERE p.deleted_at IS NULL
                ORDER BY p.nom, p.prenom
                LIMIT 10";
        try {
            $joinRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $joinErr = $e->getMessage();
        }

        // Détection catégories
        $catStats = ['delegue' => 0, 'pharmacien' => 0, 'depositaire' => 0, 'autre' => 0];
        $fonctions = $pdo->query("SELECT fonction FROM professionnels_dpm WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($fonctions as $f) $catStats[detectCat($f)]++;
    }
}

$required = ['professionnels_dpm','agences_dpm','pharmacies_dpm','depots_dpm'];
$allOk    = !$dbErr && in_array('professionnels_dpm', $tables) && !$joinErr && $totalActifs > 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Diagnostic — professionnels_dpm</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#F1F5F9;color:#1E293B;padding:32px;font-size:14px}
h1{font-size:1.3rem;font-weight:700;margin-bottom:4px}
.sub{color:#64748B;font-size:.83rem;margin-bottom:24px}
.warn{background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:11px 16px;margin-bottom:22px;font-size:.8rem;color:#92400E}

.card{background:#fff;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;margin-bottom:18px}
.card-head{display:flex;align-items:center;gap:10px;padding:12px 18px;border-bottom:1px solid #F1F5F9;background:#FAFBFC}
.card-head h2{font-size:.9rem;font-weight:600;flex:1}
.card-body{padding:16px 18px}

.badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.badge.ok{background:#D1FAE5;color:#065F46}
.badge.err{background:#FEE2E2;color:#991B1B}
.badge.warn{background:#FEF3C7;color:#92400E}

.ico{width:16px;height:16px;flex-shrink:0}
.ico.ok{color:#059669} .ico.err{color:#DC2626}

.row{display:flex;align-items:center;gap:9px;padding:6px 0;border-bottom:1px solid #F8FAFC;font-size:.83rem}
.row:last-child{border-bottom:none}

/* Tags */
.tags{display:flex;flex-wrap:wrap;gap:6px}
.tag{display:inline-block;padding:2px 9px;border-radius:5px;font-size:.72rem;font-weight:500}
.tag.present{background:#D1FAE5;color:#065F46} .tag.absent{background:#FEE2E2;color:#991B1B} .tag.neutral{background:#EFF6FF;color:#1E40AF}

/* Table */
table{width:100%;border-collapse:collapse;font-size:.8rem}
th{background:#F8FAFC;text-align:left;padding:7px 11px;font-weight:600;font-size:.75rem;color:#64748B;border-bottom:2px solid #E2E8F0}
td{padding:7px 11px;border-bottom:1px solid #F1F5F9;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FAFBFC}
.null{color:#CBD5E1;font-style:italic}
.link{background:#EFF6FF;color:#1D4ED8;border-radius:4px;padding:1px 7px}
.active{background:#D1FAE5;color:#065F46;border-radius:4px;padding:1px 7px;font-size:.72rem;font-weight:600}
.inactive{background:#F3F4F6;color:#9CA3AF;border-radius:4px;padding:1px 7px;font-size:.72rem;font-weight:600}

/* Stats catégories */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.stat{border:1px solid #E2E8F0;border-radius:10px;padding:14px 16px;text-align:center}
.stat .n{font-size:2rem;font-weight:700;line-height:1}
.stat .l{font-size:.72rem;color:#64748B;margin-top:4px}
.delegue .n{color:#1D4ED8} .pharmacien .n{color:#6D28D9} .depositaire .n{color:#B45309} .autre .n{color:#6B7280}

.err-box{background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:11px 14px;font-size:.82rem;color:#991B1B;margin-bottom:10px}
pre{background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:10px 14px;font-size:.75rem;overflow-x:auto;color:#475569;white-space:pre-wrap;word-break:break-all;margin-top:8px}

.summary{border-color:<?= $allOk ? '#6EE7B7' : '#FCA5A5' ?>}
.summary .card-head{background:<?= $allOk ? '#ECFDF5' : '#FEF2F2' ?>}
.footer{text-align:center;font-size:.72rem;color:#CBD5E1;margin-top:20px}
</style>
</head>
<body>

<h1>🔍 Diagnostic — <code>professionnels_dpm</code></h1>
<p class="sub">Fichier de vérification autonome · ne modifie aucune donnée</p>
<div class="warn">⚠️ <strong>À supprimer après utilisation</strong> — ce fichier expose des informations sur votre base de données.</div>

<!-- 1. Connexion -->
<div class="card">
  <div class="card-head">
    <h2>1. Connexion base de données</h2>
    <?= badge(!$dbErr, 'Connecté', 'Échec') ?>
  </div>
  <div class="card-body">
    <?php if ($dbErr): ?>
      <div class="err-box">❌ <?= esc($dbErr) ?></div>
      <p style="font-size:.8rem;color:#64748B">Vérifiez <code>config/database.php</code> — identifiants incorrects ou serveur MySQL inaccessible.</p>
    <?php else: ?>
      <div class="row"><?= ico(true) ?><span>PDO connecté avec succès</span></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($pdo): ?>

<!-- 2. Tables -->
<div class="card">
  <div class="card-head">
    <h2>2. Tables détectées</h2>
    <?= badge(count($tables) > 0, count($tables).' tables', '0 table') ?>
  </div>
  <div class="card-body">
    <div class="tags" style="margin-bottom:14px">
      <?php foreach ($tables as $t): ?>
        <span class="tag <?= in_array($t,$required)?'present':'neutral' ?>"><?= esc($t) ?></span>
      <?php endforeach; ?>
    </div>
    <div style="font-size:.78rem;font-weight:600;color:#64748B;margin-bottom:6px">Tables requises :</div>
    <?php foreach ($required as $t): ?>
    <div class="row">
      <?= ico(in_array($t,$tables)) ?>
      <span><code><?= $t ?></code></span>
      <?php if (!in_array($t,$tables)): ?><span style="color:#DC2626;font-size:.78rem">ABSENTE — les JOIN échoueront</span><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if (in_array('professionnels_dpm', $tables)): ?>

<!-- 3. Données brutes -->
<div class="card">
  <div class="card-head">
    <h2>3. Données brutes (sans JOIN)</h2>
    <?= badge($totalActifs > 0, $totalActifs.' enregistrements actifs', '0 enregistrement') ?>
  </div>
  <div class="card-body">
    <?php if ($totalActifs == 0): ?>
      <div class="err-box">⚠️ La table est vide ou tous les enregistrements ont <code>deleted_at</code> renseigné. Rien ne s'affichera.</div>
    <?php else: ?>
    <p style="font-size:.78rem;color:#64748B;margin-bottom:10px">10 premiers enregistrements actifs :</p>
    <table>
      <thead><tr>
        <th>ID</th><th>Prénom / Nom</th><th>Sexe</th><th>Fonction</th>
        <th>agence_id</th><th>pharmacie_id</th><th>depot_id</th>
        <th>Validité</th><th>Actif</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><strong><?= esc($r['prenom'].' '.$r['nom']) ?></strong></td>
        <td><?= esc($r['sexe']) ?></td>
        <td><?= esc($r['fonction']) ?></td>
        <td><?= $r['agence_id'] ? '<span class="link">'.$r['agence_id'].'</span>' : '<span class="null">null</span>' ?></td>
        <td><?= $r['pharmacie_id'] ? '<span class="link">'.$r['pharmacie_id'].'</span>' : '<span class="null">null</span>' ?></td>
        <td><?= $r['depot_id'] ? '<span class="link">'.$r['depot_id'].'</span>' : '<span class="null">null</span>' ?></td>
        <td><?= $r['date_validite'] ? date('d/m/Y', strtotime($r['date_validite'])) : '<span class="null">—</span>' ?></td>
        <td><span class="<?= $r['is_active'] ? 'active' : 'inactive' ?>"><?= $r['is_active'] ? 'Oui' : 'Non' ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- 4. Données avec JOIN -->
<div class="card">
  <div class="card-head">
    <h2>4. Données avec JOIN (agence · pharmacie · dépôt)</h2>
    <?= badge(!$joinErr, $joinErr ? 'ERREUR SQL' : 'OK') ?>
  </div>
  <div class="card-body">
    <?php if ($joinErr): ?>
      <div class="err-box">❌ <?= esc($joinErr) ?></div>
      <p style="font-size:.78rem;color:#64748B;margin-top:8px">Requête tentée :</p>
      <pre><?= esc($sql) ?></pre>
    <?php else: ?>
    <p style="font-size:.78rem;color:#64748B;margin-bottom:10px">
      C'est exactement la requête utilisée par <code>professionnels.php</code> — si les noms s'affichent ici, ils s'afficheront dans la page.
    </p>
    <table>
      <thead><tr>
        <th>ID</th><th>Prénom / Nom</th><th>Fonction</th>
        <th>Agence (agence_nom)</th><th>Pharmacie (pharmacie_nom)</th><th>Dépôt (depot_nom)</th>
      </tr></thead>
      <tbody>
      <?php foreach ($joinRows as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><strong><?= esc($r['prenom'].' '.$r['nom']) ?></strong></td>
        <td><?= esc($r['fonction']) ?></td>
        <td><?= $r['agence_nom']    ? '<span style="color:#059669;font-weight:500">'.esc($r['agence_nom']).'</span>'    : '<span class="null">null</span>' ?></td>
        <td><?= $r['pharmacie_nom'] ? '<span style="color:#059669;font-weight:500">'.esc($r['pharmacie_nom']).'</span>' : '<span class="null">null</span>' ?></td>
        <td><?= $r['depot_nom']     ? '<span style="color:#059669;font-weight:500">'.esc($r['depot_nom']).'</span>'     : '<span class="null">null</span>' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!empty($joinRows)): ?>
    <?php
      $noRattach = array_filter($joinRows, fn($r) => !$r['agence_nom'] && !$r['pharmacie_nom'] && !$r['depot_nom']);
    ?>
    <?php if (count($noRattach) === count($joinRows)): ?>
    <div class="err-box" style="margin-top:12px">
      ⚠️ Tous ces enregistrements ont <em>agence_nom, pharmacie_nom ET depot_nom = null</em>.
      Les colonnes de rattachement s'afficheront vides dans la page.<br>
      Vérifiez que les IDs (<code>agence_id</code>, <code>pharmacie_id</code>, <code>depot_id</code>) correspondent bien à des enregistrements existants dans les tables liées.
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- 5. Répartition catégories -->
<div class="card">
  <div class="card-head">
    <h2>5. Répartition par catégorie (détection sur la fonction)</h2>
    <?= badge($catStats['autre'] == 0, 'Tout classifié', $catStats['autre'].' non classifié(s)') ?>
  </div>
  <div class="card-body">
    <div class="stats" style="margin-bottom:14px">
      <?php foreach (['delegue'=>'Délégués médicaux','pharmacien'=>'Pharmaciens','depositaire'=>'Dépositaires','autre'=>'Non classifiés'] as $k=>$l): ?>
      <div class="stat <?= $k ?>">
        <div class="n"><?= $catStats[$k] ?? 0 ?></div>
        <div class="l"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (($catStats['autre'] ?? 0) > 0): ?>
    <div class="err-box">⚠️ <?= $catStats['autre'] ?> fonction(s) non reconnues — elles tomberont dans "autre" et n'apparaîtront dans aucun onglet.<br>
    <?php
      $all = $pdo->query("SELECT DISTINCT fonction FROM professionnels_dpm WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
      $unknowns = array_filter($all, fn($f) => detectCat($f) === 'autre');
      if ($unknowns) echo '<strong>Valeurs :</strong> ' . implode(', ', array_map('htmlspecialchars', $unknowns));
    ?>
    </div>
    <?php else: ?>
    <div class="row"><?= ico(true) ?><span>Toutes les fonctions sont classifiées. L'onglet "Délégués médicaux" s'affichera bien par défaut avec <strong><?= $catStats['delegue'] ?> délégués</strong>.</span></div>
    <?php endif; ?>
  </div>
</div>

<?php endif; // in_array professionnels_dpm ?>
<?php endif; // $pdo ?>

<!-- Résumé -->
<div class="card summary">
  <div class="card-head">
    <h2>Résumé</h2>
    <?= badge($allOk, 'Tout OK', 'Problème(s) détecté(s)') ?>
  </div>
  <div class="card-body">
    <?php if ($allOk): ?>
      <p style="font-size:.85rem;color:#065F46;font-weight:600">✅ Base accessible · table présente · <?= $totalActifs ?> enregistrements actifs · JOIN fonctionnels · catégories détectées.</p>
      <p style="font-size:.8rem;color:#64748B;margin-top:10px">
        Si <code>professionnels.php</code> reste vide malgré tout, vérifiez la console navigateur (F12) :<br>
        &nbsp;→ Le message <code>[DPM Professionnels] Données chargées : X enregistrements</code> doit apparaître.<br>
        &nbsp;→ Si X = 0, la session PHP a peut-être expiré (<code>requireLogin()</code> redirige avant de passer les données au JS).
      </p>
    <?php else: ?>
      <?php if ($dbErr): ?><div class="err-box">❌ Connexion DB impossible : <?= esc($dbErr) ?></div><?php endif; ?>
      <?php if (!in_array('professionnels_dpm', $tables)): ?><div class="err-box">❌ Table <code>professionnels_dpm</code> absente</div><?php endif; ?>
      <?php if ($joinErr): ?><div class="err-box">❌ Requête JOIN échoue : <?= esc($joinErr) ?></div><?php endif; ?>
      <?php if (!$dbErr && in_array('professionnels_dpm', $tables) && $totalActifs == 0): ?><div class="err-box">⚠️ Table vide — aucun enregistrement actif</div><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<p class="footer">Généré le <?= date('d/m/Y à H:i:s') ?> · Supprimer ce fichier après utilisation</p>
</body>
</html>