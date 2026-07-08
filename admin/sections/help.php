<?php
declare(strict_types=1);
?>
<h2>Aide — Guide d'utilisation</h2>
<p class="hint">Ce panneau permet de modifier le site sans toucher au code. Chaque enregistrement est appliqué immédiatement — il n'y a ni build ni déploiement à lancer.</p>

<div class="admin-card">
  <h3>Sommaire</h3>
  <ul class="admin-help-toc">
    <li><a href="#help-general">Généralités</a></li>
    <li><a href="#help-pages">Pages</a></li>
    <li><a href="#help-seo">SEO &amp; Schema</a></li>
    <li><a href="#help-media">Médiathèque</a></li>
    <li><a href="#help-favicon">Favicon &amp; Logo</a></li>
    <li><a href="#help-pdf">PDF (lead magnet)</a></li>
    <li><a href="#help-affiliates">Liens affiliés</a></li>
    <li><a href="#help-blog">Blog</a></li>
    <li><a href="#help-account">Mon compte</a></li>
  </ul>
</div>

<div class="admin-card" id="help-general">
  <h3>Généralités</h3>
  <p>Connectez-vous avec votre identifiant et mot de passe admin. Le bouton <strong>« Voir le site ↗ »</strong> en haut de la barre latérale ouvre le site public dans un nouvel onglet, pratique pour vérifier un changement juste après l'avoir enregistré.</p>
  <p>Après chaque enregistrement, un bandeau s'affiche en haut de la section : <strong>vert</strong> = succès, <strong>rouge</strong> = erreur (avec le détail du problème). Tant qu'un bandeau vert n'apparaît pas, la modification n'est pas enregistrée.</p>
  <p>Le site est composé de fichiers statiques modifiés directement — il n'y a pas de base de données ni d'étape de publication séparée : ce que vous enregistrez ici est immédiatement visible sur le site.</p>
</div>

<div class="admin-card" id="help-pages">
  <h3>Pages</h3>
  <p>Modifie les textes et images de l'accueil, du quiz et de la page résultat. Utilisez les onglets en haut pour choisir la page.</p>
  <a href="assets/screenshots/pages.png" target="_blank" rel="noopener noreferrer">
    <img class="admin-help-screenshot" src="assets/screenshots/pages.png" alt="Capture d'écran de la section Pages" loading="lazy" />
  </a>
  <ol>
    <li>Choisissez la page à modifier dans les onglets.</li>
    <li>Chaque champ affiche son identifiant technique entre parenthèses — utile uniquement si vous devez le retrouver ailleurs (ex. dans le catalogue de liens affiliés).</li>
    <li>Pour une image, indiquez le chemin (ex. <code>assets/images/exemple.webp</code>) — la liste déroulante propose les fichiers déjà présents dans la Médiathèque.</li>
    <li>Cliquez sur <strong>Enregistrer</strong>.</li>
  </ol>
</div>

<div class="admin-card" id="help-seo">
  <h3>SEO &amp; Schema</h3>
  <p>Deux types de réglages ici :</p>
  <ul>
    <li><strong>Global</strong> (premier onglet) : Google Tag Manager, vérification Google Search Console / Bing, et Meta Pixel. Ces réglages sont injectés automatiquement sur <em>toutes</em> les pages dès l'enregistrement — laissez un champ vide pour désactiver la fonctionnalité correspondante.</li>
    <li><strong>Par page</strong> (autres onglets) : titre, meta description et autres balises SEO propres à chaque page, ainsi que le JSON-LD (schéma structuré) brut de la page.</li>
  </ul>
  <a href="assets/screenshots/seo.png" target="_blank" rel="noopener noreferrer">
    <img class="admin-help-screenshot" src="assets/screenshots/seo.png" alt="Capture d'écran de la section SEO &amp; Schema" loading="lazy" />
  </a>
  <p class="hint">Le champ JSON-LD doit rester un JSON valide, sinon l'enregistrement échouera avec un message d'erreur. La page Blog n'a pas de champ schéma ici : son JSON-LD est régénéré automatiquement par le gestionnaire de Blog à chaque publication/modification d'article.</p>
</div>

<div class="admin-card" id="help-media">
  <h3>Médiathèque</h3>
  <p>Bibliothèque centrale de toutes les images utilisées sur le site.</p>
  <a href="assets/screenshots/media.png" target="_blank" rel="noopener noreferrer">
    <img class="admin-help-screenshot" src="assets/screenshots/media.png" alt="Capture d'écran de la Médiathèque" loading="lazy" />
  </a>
  <ol>
    <li>Téléversez une image (WebP, PNG, JPG ou SVG, 4 Mo max) — les PNG/JPG sont automatiquement convertis en WebP optimisé.</li>
    <li>Le chemin affiché sous chaque vignette (ex. <code>assets/images/mon-fichier.webp</code>) est celui à copier dans n'importe quel champ « image » ou « icône » ailleurs dans le panneau.</li>
    <li>Supprimer une image ici la retire du serveur — vérifiez qu'elle n'est plus référencée nulle part avant de la supprimer, sinon l'image cassera là où elle était utilisée.</li>
  </ol>
</div>

<div class="admin-card" id="help-favicon">
  <h3>Favicon &amp; Logo</h3>
  <p>Le favicon (icône d'onglet) et le logo affiché dans l'en-tête sont gérés séparément des autres images : chaque nouvel envoi remplace automatiquement l'ancien fichier et se synchronise sur toutes les pages sans autre action.</p>
  <a href="assets/screenshots/favicon.png" target="_blank" rel="noopener noreferrer">
    <img class="admin-help-screenshot" src="assets/screenshots/favicon.png" alt="Capture d'écran de la section Favicon &amp; Logo" loading="lazy" />
  </a>
  <ul>
    <li><strong>Favicon</strong> : ICO, PNG ou SVG, 1 Mo max.</li>
    <li><strong>Logo</strong> : WebP, PNG, JPG ou SVG, 2 Mo max (converti en WebP optimisé si besoin).</li>
  </ul>
</div>

<div class="admin-card" id="help-pdf">
  <h3>PDF (lead magnet)</h3>
  <p>Le PDF téléchargé par les visiteurs après avoir rempli le formulaire (plan d'action). Il n'est <strong>jamais</strong> accessible par une URL directe — il n'est servi qu'après une soumission de formulaire réussie, via un lien à durée de vie limitée.</p>
  <a href="assets/screenshots/pdf.png" target="_blank" rel="noopener noreferrer">
    <img class="admin-help-screenshot" src="assets/screenshots/pdf.png" alt="Capture d'écran de la section PDF (lead magnet)" loading="lazy" />
  </a>
  <p>Pour le remplacer, téléversez simplement un nouveau PDF (15 Mo max) : il prend effet immédiatement pour tous les prochains téléchargements.</p>
</div>

<div class="admin-card" id="help-affiliates">
  <h3>Liens affiliés</h3>
  <p>Alimente les 2 cartes logiciel affichées sur la page Résultat. Il existe exactement <strong>3 priorités fixes</strong> (correspondant à la réponse donnée à l'Étape 2 du quiz), chacune avec exactement <strong>2 emplacements</strong> — on ne peut pas en ajouter ou en retirer, seulement modifier leur contenu.</p>
  <a href="assets/screenshots/affiliates.png" target="_blank" rel="noopener noreferrer">
    <img class="admin-help-screenshot" src="assets/screenshots/affiliates.png" alt="Capture d'écran de la section Liens affiliés" loading="lazy" />
  </a>
  <p>Pour chaque emplacement : <strong>Nom</strong> (obligatoire), <strong>Icône</strong> (chemin d'image, cf. Médiathèque), <strong>Badge</strong> (ex. « Partenaire »), <strong>Description</strong>, <strong>Lien affilié</strong> (URL de destination) et un <strong>Texte du bouton</strong> optionnel (par défaut « Découvrir → »).</p>
</div>

<div class="admin-card" id="help-blog">
  <h3>Blog</h3>
  <p>Gère les articles du blog. La liste des articles et le schéma JSON-LD de la page Blog sont régénérés automatiquement à chaque enregistrement ou suppression — rien à faire côté SEO.</p>
  <a href="assets/screenshots/blog.png" target="_blank" rel="noopener noreferrer">
    <img class="admin-help-screenshot" src="assets/screenshots/blog.png" alt="Capture d'écran de la section Blog" loading="lazy" />
  </a>
  <ol>
    <li>Cliquez sur <strong>Nouvel article</strong> ou sur un article existant pour l'éditer.</li>
    <li>Le <strong>slug</strong> détermine l'URL (<code>/blog-&lt;slug&gt;.html</code>) — uniquement minuscules, chiffres et tirets. Changer le slug d'un article existant renomme sa page.</li>
    <li>Le <strong>Badge</strong> sert de catégorie ; choisissez-en un existant ou créez-en un nouveau via « + Nouveau badge… ».</li>
    <li>Construisez le contenu avec les blocs (texte, image, vidéo) — ils peuvent être réordonnés, et un aperçu s'affiche pour les images/vidéos.</li>
    <li>Enregistrez : l'article est publié, et la page Blog (grille + schéma) est mise à jour automatiquement.</li>
    <li>Supprimer un article retire sa page et la grille se régénère automatiquement.</li>
  </ol>
</div>

<div class="admin-card" id="help-account">
  <h3>Mon compte</h3>
  <p>Permet de changer le mot de passe admin (10 caractères minimum). Il faut saisir le mot de passe actuel pour confirmer le changement.</p>
  <a href="assets/screenshots/account.png" target="_blank" rel="noopener noreferrer">
    <img class="admin-help-screenshot" src="assets/screenshots/account.png" alt="Capture d'écran de la section Mon compte" loading="lazy" />
  </a>
</div>
