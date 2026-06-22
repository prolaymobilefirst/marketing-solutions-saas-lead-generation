<?php
declare(strict_types=1);

/* The fixed set of HTML flat files the "Pages" and "SEO" admin sections are
   allowed to touch — a whitelist, never derived from request input, so a
   crafted "file" parameter can't be used to read/patch an arbitrary path. */
const CMS_EDITABLE_FILES = [
    'index'   => ['label' => 'Accueil',   'path' => 'index.html'],
    'quiz'    => ['label' => 'Quiz',      'path' => 'quiz.html'],
    'results' => ['label' => 'Résultat',  'path' => 'results.html'],
    'blog'    => ['label' => 'Blog (index)', 'path' => 'blog.html'],
];

function cms_file_path(string $fileKey): string
{
    if (!isset(CMS_EDITABLE_FILES[$fileKey])) {
        throw new InvalidArgumentException('Unknown page');
    }
    return __DIR__ . '/../../' . CMS_EDITABLE_FILES[$fileKey]['path'];
}
