<?php
declare(strict_types=1);

/* Surgical [data-cms] scan + patch for the static HTML flat files.
   Deliberately NOT a DOMDocument round-trip: re-serializing a whole HTML5
   file through DOMDocument->saveHTML() reformats everything (entities,
   self-closing void tags, attribute quoting) even for untouched markup,
   which would wreck the pixel-perfect/minimal-diff goal. Instead this
   regex-patches only the exact byte range belonging to the targeted field,
   leaving the rest of the file untouched. This is safe specifically
   because *we* control every data-cms attribute added to the markup
   (single, fixed convention below) — this is not a general HTML parser.

   Convention on a tagged element:
     data-cms="some.key"                  required, unique key
     data-cms-kind="text|attr|json"        optional, defaults to "text"
     data-cms-attr="src|href|content|alt"  required when kind="attr"
     data-cms-label="Human label"          optional, shown in the admin form

   - kind "text": the element is a leaf (no nested tags) — its text content
     between the opening tag's ">" and the next "<" is the editable value.
   - kind "attr": the named attribute's value is the editable value.
   - kind "json": the element is a <script type="application/ld+json">
     block — its raw JSON text is the editable value. */

class CmsField
{
    public function __construct(
        public string $key,
        public string $kind,
        public ?string $attr,
        public ?string $label,
        public string $value,
    ) {}
}

function cms_open_tag_regex(string $key): string
{
    $k = preg_quote($key, '/');
    return '/<([a-zA-Z0-9]+)\b((?:[^>"]|"[^"]*")*?)\sdata-cms="' . $k . '"((?:[^>"]|"[^"]*")*?)>/s';
}

// Text content only needs &/</> escaped — quotes are irrelevant outside
// attribute values, so ENT_NOQUOTES avoids needlessly turning every
// apostrophe into &#039; and changing the file's existing style. Re-encode
// literal NBSP (as decoded by cms_scan() from &nbsp;) back to the named
// entity too, for the same reason.
function cms_escape_text(string $value): string
{
    return str_replace("\xc2\xa0", '&nbsp;', htmlspecialchars($value, ENT_NOQUOTES | ENT_HTML5));
}

function cms_attr_value(string $tagAttrs, string $name): ?string
{
    if (preg_match('/\b' . preg_quote($name, '/') . '="([^"]*)"/', $tagAttrs, $m)) {
        return $m[1];
    }
    return null;
}

/** @return CmsField[] every data-cms field found in $html, in document order */
function cms_scan(string $html): array
{
    $fields = [];
    if (!preg_match_all('/<([a-zA-Z0-9]+)\b((?:[^>"]|"[^"]*")*?)\sdata-cms="([^"]+)"((?:[^>"]|"[^"]*")*?)(\/?)>/s', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $fields;
    }

    foreach ($matches as $m) {
        $tag = $m[1][0];
        $attrsCombined = $m[2][0] . ' ' . $m[4][0];
        $key = $m[3][0];
        $kind = cms_attr_value($attrsCombined, 'data-cms-kind') ?? 'text';
        $attrName = cms_attr_value($attrsCombined, 'data-cms-attr');
        $label = cms_attr_value($attrsCombined, 'data-cms-label');
        $tagEndOffset = $m[0][1] + strlen($m[0][0]);

        if ($kind === 'attr') {
            $value = $attrName !== null ? (cms_attr_value($attrsCombined, $attrName) ?? '') : '';
        } elseif ($kind === 'json' || $kind === 'html') {
            $closeTag = '</' . $tag . '>';
            $closePos = strpos($html, $closeTag, $tagEndOffset);
            $value = $closePos !== false ? substr($html, $tagEndOffset, $closePos - $tagEndOffset) : '';
        } else { // text
            $nextLt = strpos($html, '<', $tagEndOffset);
            $value = $nextLt !== false ? html_entity_decode(substr($html, $tagEndOffset, $nextLt - $tagEndOffset), ENT_QUOTES) : '';
        }

        $fields[] = new CmsField($key, $kind, $attrName, $label, trim($value));
    }

    return $fields;
}

/**
 * Patches a single data-cms field in $html and returns the new HTML.
 * Caller must already know the field's kind/attr (from cms_scan()).
 */
function cms_patch_field(string $html, string $key, string $kind, ?string $attrName, string $newValue): string
{
    $openTagRe = cms_open_tag_regex($key);

    if ($kind === 'attr') {
        if ($attrName === null) {
            throw new InvalidArgumentException("Field '$key' has kind=attr but no attrName");
        }
        return preg_replace_callback($openTagRe, function (array $m) use ($attrName, $newValue) {
            $fullTag = $m[0];
            // ENT_COMPAT (not ENT_QUOTES): every attribute in this codebase
            // is double-quote delimited, so only " needs escaping here —
            // matches the existing style instead of turning every
            // apostrophe into &#039;.
            $escaped = htmlspecialchars($newValue, ENT_COMPAT);
            $attrRe = '/\b' . preg_quote($attrName, '/') . '="[^"]*"/';
            if (preg_match($attrRe, $fullTag)) {
                return preg_replace($attrRe, $attrName . '="' . $escaped . '"', $fullTag, 1);
            }
            return substr($fullTag, 0, -1) . ' ' . $attrName . '="' . $escaped . '">';
        }, $html, 1);
    }

    if (!preg_match($openTagRe, $html, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException("data-cms key '$key' not found");
    }
    $tag = $m[1][0];
    $tagEndOffset = $m[0][1] + strlen($m[0][0]);

    if ($kind === 'json' || $kind === 'html') {
        // Both are "raw block" kinds — content is substituted verbatim, no
        // escaping. "json" content is validated by the caller before this
        // is reached; "html" is trusted admin-authored markup (the admin
        // already has full filesystem write access via this panel, so
        // there's no privilege boundary being crossed by trusting it).
        $closeTag = '</' . $tag . '>';
        $closePos = strpos($html, $closeTag, $tagEndOffset);
        if ($closePos === false) {
            throw new RuntimeException("Closing tag for '$key' not found");
        }
        return substr($html, 0, $tagEndOffset) . $newValue . substr($html, $closePos);
    }

    // text
    $nextLt = strpos($html, '<', $tagEndOffset);
    if ($nextLt === false) {
        throw new RuntimeException("No closing tag found for '$key'");
    }
    return substr($html, 0, $tagEndOffset) . cms_escape_text($newValue) . substr($html, $nextLt);
}

/** Apply several field updates (key => newValue) to $html in one pass. */
function cms_patch_many(string $html, array $fieldsByKey, array $updates): string
{
    foreach ($updates as $key => $newValue) {
        if (!isset($fieldsByKey[$key])) {
            continue;
        }
        $f = $fieldsByKey[$key];
        $html = cms_patch_field($html, $key, $f->kind, $f->attr, $newValue);
    }
    return $html;
}

/** @return array<string,CmsField> */
function cms_scan_keyed(string $html): array
{
    $out = [];
    foreach (cms_scan($html) as $f) {
        $out[$f->key] = $f;
    }
    return $out;
}
