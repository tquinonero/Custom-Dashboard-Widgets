---
name: block-schemas
description: >
  Formal Gutenberg block schemas for AI block generation. Contains the
  stripped block.json for each core block — attributes, defaults, supports,
  and serialization rules — plus validation notes for fields where the schema
  alone is ambiguous. Read this skill before generating any block markup.
---

# Gutenberg Block Schemas

Schemas are stripped of editor-only fields (editorStyle, style, selectors,
apiVersion, textdomain, usesContext, __experimentalDefaultControls). What
remains is everything that affects block comment attributes and saved HTML.

**How to use this skill**

1. Find the block section below.
2. Read the `attributes` object — these are the valid keys for the block
   comment JSON. Only include attributes that produce HTML output.
3. Read the `supports` object — this tells you what style controls are
   available (color, spacing, typography, border, etc.).
4. Read the **Validation notes** — these cover edge cases where the schema
   alone is ambiguous or where a common mistake causes the block validator
   to reject the markup.
5. Generate the block comment JSON using only attributes from the schema.
   The HTML must match exactly what those attributes produce — any extra
   class, style, or element that is not derivable from the comment attributes
   will fail validation.

**Regenerating this file**

Run `node generate-block-schemas.js --refresh` to pull fresh schemas from
the Gutenberg GitHub repository (requires network access).
Run without `--refresh` to rebuild from the bundled snapshots (offline-safe).

---

## core/paragraph

```json
{
  "name": "core/paragraph",
  "attributes": {
    "align": {
      "type": "string"
    },
    "content": {
      "type": "rich-text",
      "source": "rich-text",
      "selector": "p"
    },
    "dropCap": {
      "type": "boolean",
      "default": false
    },
    "placeholder": {
      "type": "string"
    },
    "textColor": {
      "type": "string"
    },
    "backgroundColor": {
      "type": "string"
    },
    "fontSize": {
      "type": "string"
    },
    "direction": {
      "type": "string",
      "enum": [
        "ltr",
        "rtl"
      ]
    },
    "style": {
      "type": "object"
    }
  },
  "supports": {
    "anchor": true,
    "className": false,
    "color": {
      "gradients": true,
      "link": true
    },
    "spacing": {
      "margin": true,
      "padding": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalTextDecoration": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextTransform": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true
    }
  }
}
```

### Validation notes

- `align` is the text-alignment attribute for paragraphs (not `textAlign`).
  Valid values: "left", "center", "right".
- Named palette slugs in `textColor` / `backgroundColor` produce
  `has-{slug}-color` / `has-{slug}-background-color` classes.
  Use hex in `style.color.text` / `style.color.background` instead to avoid
  palette dependency.
- Inline padding goes in `style.spacing.padding`, not as a top-level attribute.

---

## core/heading

```json
{
  "name": "core/heading",
  "attributes": {
    "textAlign": {
      "type": "string"
    },
    "content": {
      "type": "rich-text",
      "source": "rich-text",
      "selector": "h1,h2,h3,h4,h5,h6"
    },
    "level": {
      "type": "number",
      "default": 2
    },
    "levelOptions": {
      "type": "array"
    },
    "placeholder": {
      "type": "string"
    },
    "textColor": {
      "type": "string"
    },
    "backgroundColor": {
      "type": "string"
    },
    "gradient": {
      "type": "string"
    },
    "fontSize": {
      "type": "string"
    },
    "style": {
      "type": "object"
    }
  },
  "supports": {
    "align": [
      "wide",
      "full"
    ],
    "anchor": true,
    "className": true,
    "color": {
      "gradients": true,
      "link": true
    },
    "spacing": {
      "margin": true,
      "padding": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextDecoration": true,
      "__experimentalTextTransform": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true
    }
  }
}
```

### Validation notes

- Use `textAlign` for inline text alignment ("left", "center", "right").
  Use `align` only for layout alignment ("wide", "full").
  These are different attributes — mixing them causes validation failure.
- `level` defaults to 2. Omit it when emitting an h2 to keep markup clean.
- Named `textColor` palette slug produces `has-{slug}-color has-text-color`
  classes. Use `style.color.text` with a hex value to avoid palette dependency.
- `fontSize` is a theme-registered slug (e.g. "large", "x-large"). For exact
  pixel sizes use `style.typography.fontSize` instead.

---

## core/image

```json
{
  "name": "core/image",
  "attributes": {
    "align": {
      "type": "string"
    },
    "url": {
      "type": "string",
      "source": "attribute",
      "selector": "img",
      "attribute": "src"
    },
    "alt": {
      "type": "string",
      "source": "attribute",
      "selector": "img",
      "attribute": "alt",
      "default": ""
    },
    "caption": {
      "type": "rich-text",
      "source": "rich-text",
      "selector": "figcaption"
    },
    "title": {
      "type": "string",
      "source": "attribute",
      "selector": "img",
      "attribute": "title"
    },
    "href": {
      "type": "string",
      "source": "attribute",
      "selector": "figure > a",
      "attribute": "href"
    },
    "rel": {
      "type": "string",
      "source": "attribute",
      "selector": "figure > a",
      "attribute": "rel"
    },
    "linkClass": {
      "type": "string",
      "source": "attribute",
      "selector": "figure > a",
      "attribute": "class"
    },
    "id": {
      "type": "number"
    },
    "width": {
      "type": "string"
    },
    "height": {
      "type": "string"
    },
    "aspectRatio": {
      "type": "string"
    },
    "scale": {
      "type": "string"
    },
    "sizeSlug": {
      "type": "string"
    },
    "linkDestination": {
      "type": "string"
    },
    "linkTarget": {
      "type": "string",
      "source": "attribute",
      "selector": "figure > a",
      "attribute": "target"
    }
  },
  "supports": {
    "align": true,
    "anchor": true,
    "color": {
      "text": false,
      "background": false
    },
    "filter": {
      "duotone": true
    },
    "shadow": true,
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true
    }
  }
}
```

### Validation notes

- `sizeSlug` (e.g. "full", "large", "medium") drives the `size-{slug}` figure
  class. Always include it — omitting it makes the figure class unpredictable.
- `linkDestination` should always be set explicitly: "none", "media", "attachment",
  or "custom". Omitting it causes the validator to assume a default that may not
  match the rendered HTML.
- The attachment ID class `wp-image-{id}` goes on the `<img>` element, not
  the `<figure>`. Include `id` in attrs when you have it.
- `caption` is stored as an attribute AND rendered as `<figcaption>` — both
  must be present or validation fails.

---

## core/cover

```json
{
  "name": "core/cover",
  "attributes": {
    "url": {
      "type": "string"
    },
    "useFeaturedImage": {
      "type": "boolean",
      "default": false
    },
    "id": {
      "type": "number"
    },
    "alt": {
      "type": "string",
      "default": ""
    },
    "hasParallax": {
      "type": "boolean",
      "default": false
    },
    "isRepeated": {
      "type": "boolean",
      "default": false
    },
    "dimRatio": {
      "type": "number",
      "default": 100
    },
    "overlayColor": {
      "type": "string"
    },
    "customOverlayColor": {
      "type": "string"
    },
    "isUserOverlayColor": {
      "type": "boolean"
    },
    "backgroundType": {
      "type": "string",
      "default": "image"
    },
    "focalPoint": {
      "type": "object"
    },
    "minHeight": {
      "type": "number"
    },
    "minHeightUnit": {
      "type": "string"
    },
    "gradient": {
      "type": "string"
    },
    "customGradient": {
      "type": "string"
    },
    "contentPosition": {
      "type": "string"
    },
    "isDark": {
      "type": "boolean",
      "default": true
    },
    "templateLock": {
      "type": [
        "string",
        "boolean"
      ],
      "enum": [
        "all",
        "insert",
        "contentOnly",
        false
      ]
    },
    "tagName": {
      "type": "string",
      "default": "div"
    },
    "sizeSlug": {
      "type": "string"
    },
    "poster": {
      "type": "string",
      "source": "attribute",
      "selector": "video",
      "attribute": "poster"
    }
  },
  "supports": {
    "anchor": true,
    "align": true,
    "html": false,
    "shadow": true,
    "spacing": {
      "padding": true,
      "margin": [
        "top",
        "bottom"
      ],
      "blockGap": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true
    },
    "color": {
      "heading": true,
      "text": true,
      "background": false,
      "__experimentalSkipSerialization": [
        "gradients"
      ],
      "enableContrastChecker": false
    },
    "dimensions": {
      "aspectRatio": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontWeight": true,
      "__experimentalFontStyle": true,
      "__experimentalTextTransform": true,
      "__experimentalTextDecoration": true,
      "__experimentalLetterSpacing": true
    },
    "layout": {
      "allowJustification": false
    },
    "filter": {
      "duotone": true
    },
    "allowedBlocks": true
  }
}
```

### Validation notes

- Use `customOverlayColor` (hex string) instead of `overlayColor` (palette slug).
  `overlayColor` requires the theme to register that palette name — it will cause
  a validation failure on themes that don't. `customOverlayColor` works everywhere.
- `dimRatio` defaults to 100 (fully opaque overlay). The `has-background-dim-{N}`
  HTML class uses the value rounded to the nearest 10. Store the exact value in the
  attr; the class uses the rounded step.
- `minHeight` and `minHeightUnit` must both be set. The inline style value must
  exactly match `{minHeight}{minHeightUnit}` (e.g. attr 600 + "px" → style "600px").
- `isDark` defaults to true — Gutenberg expects light-coloured inner content when
  this is true. Set to false when using a light overlay so inner text can be dark.
- `contentPosition` controls inner content alignment. Valid values:
  "top left", "top center", "top right",
  "center left", "center center", "center right",
  "bottom left", "bottom center", "bottom right".
- `color.__experimentalSkipSerialization: ["gradients"]` means gradient values
  are NOT auto-serialized into classes by the style engine — the cover block handles
  gradient output itself. Do not add gradient classes manually.
- `backgroundType` defaults to "image". Set to "video" when using a video URL.
  When "video", the media element is `<video>` not `<img>`.
- `poster` is sourced from the `<video poster="...">` HTML attribute, not from
  the block comment JSON. If you need a poster, set it as an HTML attribute on the
  video element.
- The overlay `<span>` must carry both `has-background-dim` and
  `has-background-dim-{step}` classes, plus `style="background-color:{hex}"`
  when using `customOverlayColor`.

---

## core/group

```json
{
  "name": "core/group",
  "attributes": {
    "tagName": {
      "type": "string",
      "default": "div"
    },
    "templateLock": {
      "type": [
        "string",
        "boolean"
      ],
      "enum": [
        "all",
        "insert",
        "contentOnly",
        false
      ]
    },
    "allowedBlocks": {
      "type": "array"
    },
    "style": {
      "type": "object"
    },
    "backgroundColor": {
      "type": "string"
    },
    "textColor": {
      "type": "string"
    },
    "gradient": {
      "type": "string"
    }
  },
  "supports": {
    "align": [
      "wide",
      "full"
    ],
    "anchor": true,
    "ariaLabel": true,
    "html": false,
    "color": {
      "gradients": true,
      "link": true,
      "heading": true,
      "button": true
    },
    "spacing": {
      "margin": [
        "top",
        "bottom"
      ],
      "padding": true,
      "blockGap": true
    },
    "dimensions": {
      "minHeight": true
    },
    "layout": {
      "allowSwitching": false,
      "allowInheriting": false,
      "allowEditing": true,
      "default": {
        "type": "constrained"
      }
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextDecoration": true,
      "__experimentalTextTransform": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true
    },
    "shadow": true,
    "background": {
      "backgroundImage": true,
      "backgroundSize": true
    }
  }
}
```

### Validation notes

- `tagName` defaults to "div". Common alternatives: "section", "article",
  "aside", "header", "footer", "main". Always declare it explicitly if not "div"
  so the validator knows what wrapper to expect.
- Custom hex background goes in `style.color.background` (produces inline style).
  Named palette slug goes in `backgroundColor` (produces palette class).
  Never mix both for the same block.
- Padding goes in `style.spacing.padding` as an object:
  `{"top":"48px","right":"40px","bottom":"48px","left":"40px"}`.
  The inline style is generated from this automatically.
- `layout` controls inner block flow. For a constrained centered layout:
  `{"type":"constrained"}`. For full-width flex:
  `{"type":"flex","flexWrap":"nowrap"}`.

---

## core/columns

```json
{
  "name": "core/columns",
  "attributes": {
    "verticalAlignment": {
      "type": "string"
    },
    "isStackedOnMobile": {
      "type": "boolean",
      "default": true
    },
    "templateLock": {
      "type": [
        "string",
        "boolean"
      ],
      "enum": [
        "all",
        "insert",
        "contentOnly",
        false
      ]
    },
    "allowedBlocks": {
      "type": "array"
    },
    "style": {
      "type": "object"
    },
    "backgroundColor": {
      "type": "string"
    },
    "textColor": {
      "type": "string"
    },
    "gradient": {
      "type": "string"
    }
  },
  "supports": {
    "anchor": true,
    "align": [
      "wide",
      "full"
    ],
    "html": false,
    "color": {
      "gradients": true,
      "link": true,
      "heading": true
    },
    "spacing": {
      "blockGap": {
        "top": true,
        "left": true
      },
      "margin": [
        "top",
        "bottom"
      ],
      "padding": true
    },
    "layout": {
      "allowSwitching": false,
      "allowInheriting": false,
      "allowEditing": false,
      "default": {
        "type": "flex",
        "flexWrap": "nowrap"
      }
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextDecoration": true,
      "__experimentalTextTransform": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true
    },
    "shadow": true
  }
}
```

### Validation notes

- `isStackedOnMobile` defaults to true. Always declare it explicitly so the
  validator output is deterministic.
- Column widths go on the individual `core/column` children via their `width`
  attribute, not on `core/columns`.
- Custom hex background follows the same rule as `core/group`:
  hex → `style.color.background`, palette slug → `backgroundColor`.
- Padding goes in `style.spacing.padding`. Column gap goes in
  `style.spacing.blockGap`.
- The wrapper div class is `wp-block-columns` plus optional `alignwide` /
  `alignfull` from the `align` attr.

---

## core/column

```json
{
  "name": "core/column",
  "attributes": {
    "verticalAlignment": {
      "type": "string"
    },
    "width": {
      "type": "string"
    },
    "allowedBlocks": {
      "type": "array"
    },
    "templateLock": {
      "type": [
        "string",
        "boolean"
      ],
      "enum": [
        "all",
        "insert",
        "contentOnly",
        false
      ]
    },
    "style": {
      "type": "object"
    },
    "backgroundColor": {
      "type": "string"
    },
    "textColor": {
      "type": "string"
    },
    "gradient": {
      "type": "string"
    }
  },
  "supports": {
    "anchor": true,
    "ariaLabel": true,
    "color": {
      "gradients": true,
      "link": true,
      "heading": true
    },
    "spacing": {
      "blockGap": true,
      "padding": true
    },
    "layout": {
      "allowSwitching": false,
      "allowInheriting": false,
      "default": {
        "type": "flow"
      }
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextDecoration": true,
      "__experimentalTextTransform": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true
    },
    "shadow": true,
    "html": false
  }
}
```

### Validation notes

- `width` drives the `flex-basis` inline style on the wrapper div.
  The value must include a unit: "33.33%", "50%", "400px".
  The stored attr value and the inline style value must match exactly.
- `verticalAlignment` produces an `is-vertically-aligned-{value}` class.
  Valid values: "top", "center", "bottom".
- Omitting `width` on all columns produces equal-width columns (browser default
  flex behaviour). Only set `width` when you need unequal columns.

---

## core/buttons

```json
{
  "name": "core/buttons",
  "attributes": {
    "style": {
      "type": "object"
    }
  },
  "supports": {
    "anchor": true,
    "align": [
      "wide",
      "full"
    ],
    "html": false,
    "color": {
      "gradients": false
    },
    "spacing": {
      "blockGap": true,
      "margin": [
        "top",
        "bottom"
      ],
      "padding": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextTransform": true,
      "__experimentalTextDecoration": true
    },
    "__experimentalBorder": {
      "radius": true
    },
    "layout": {
      "default": {
        "type": "flex",
        "justifyContent": "left"
      }
    }
  }
}
```

### Validation notes

- `layout` should always be set. Default flex layout:
  `{"type":"flex","justifyContent":"center"}`.
  Other justification values: "left", "right", "space-between".
- Individual button blocks go inside as `core/button` children — they are
  inner blocks, not an attribute.
- No colour attributes belong on the `core/buttons` wrapper — colours go on
  each `core/button` child.

---

## core/button

```json
{
  "name": "core/button",
  "attributes": {
    "tagName": {
      "type": "string",
      "enum": [
        "a",
        "button"
      ],
      "default": "a"
    },
    "textAlign": {
      "type": "string"
    },
    "url": {
      "type": "string",
      "source": "attribute",
      "selector": "a",
      "attribute": "href"
    },
    "title": {
      "type": "string",
      "source": "attribute",
      "selector": "a",
      "attribute": "title"
    },
    "text": {
      "type": "rich-text",
      "source": "rich-text",
      "selector": "a,button"
    },
    "linkTarget": {
      "type": "string",
      "source": "attribute",
      "selector": "a",
      "attribute": "target"
    },
    "rel": {
      "type": "string",
      "source": "attribute",
      "selector": "a",
      "attribute": "rel"
    },
    "placeholder": {
      "type": "string"
    },
    "backgroundColor": {
      "type": "string"
    },
    "textColor": {
      "type": "string"
    },
    "gradient": {
      "type": "string"
    },
    "width": {
      "type": "number"
    },
    "style": {
      "type": "object"
    }
  },
  "supports": {
    "anchor": true,
    "color": {
      "gradients": true,
      "__experimentalSkipSerialization": true,
      "text": true,
      "background": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextDecoration": true,
      "__experimentalTextTransform": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true,
      "__experimentalSkipSerialization": true
    },
    "html": false,
    "shadow": {
      "__experimentalSkipSerialization": true
    },
    "spacing": {
      "padding": [
        "horizontal",
        "vertical"
      ],
      "__experimentalSkipSerialization": true
    }
  },
  "styles": [
    {
      "name": "fill",
      "label": "Fill",
      "isDefault": true
    },
    {
      "name": "outline",
      "label": "Outline"
    }
  ]
}
```

### Validation notes

- `backgroundColor` / `textColor`: use palette slugs for theme colours
  (produces `has-{slug}-background-color` class), OR use
  `style.color.background` / `style.color.text` for hex values
  (produces inline style). Never mix both approaches for the same property.
- Outline style: set `className: "is-style-outline"`. The wrapper div gets
  class `wp-block-button is-style-outline`. Outline buttons MUST have
  `textColor` or `style.color.text` set — otherwise the text is invisible.
- `linkTarget` is "_blank" for new tab. When "_blank", `rel` should be
  "noreferrer noopener" for security.
- `width` is a percentage for full-width buttons: 25, 50, 75, or 100.
  Produces `has-custom-width wp-block-button__width-{value}` classes.
- The `<a>` tag must carry `wp-block-button__link wp-element-button` classes
  always, plus any colour classes derived from attrs.

---

## core/list

```json
{
  "name": "core/list",
  "attributes": {
    "ordered": {
      "type": "boolean",
      "default": false
    },
    "values": {
      "type": "string",
      "source": "raw",
      "selector": "ol,ul",
      "default": ""
    },
    "type": {
      "type": "string"
    },
    "start": {
      "type": "number"
    },
    "reversed": {
      "type": "boolean"
    },
    "placeholder": {
      "type": "string"
    },
    "style": {
      "type": "object"
    },
    "backgroundColor": {
      "type": "string"
    },
    "textColor": {
      "type": "string"
    },
    "gradient": {
      "type": "string"
    },
    "fontSize": {
      "type": "string"
    }
  },
  "supports": {
    "anchor": true,
    "className": true,
    "color": {
      "gradients": true,
      "link": true
    },
    "spacing": {
      "padding": true,
      "margin": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextDecoration": true,
      "__experimentalTextTransform": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true
    }
  }
}
```

### Validation notes

- Since WordPress 6.0, list items are inner blocks (`core/list-item`), not
  bare `<li>` tags. Bare `<li>` without block comment delimiters are parsed
  as a Classic block.
- `ordered` defaults to false (unordered list). Set to true for `<ol>`.
- `start` sets the starting number for ordered lists.
- `reversed` reverses the count direction for ordered lists.
- Each item needs: `<!-- wp:list-item --><li>content</li><!-- /wp:list-item -->`

---

## core/list-item

```json
{
  "name": "core/list-item",
  "attributes": {
    "placeholder": {
      "type": "string"
    },
    "content": {
      "type": "rich-text",
      "source": "rich-text",
      "selector": "li"
    },
    "style": {
      "type": "object"
    },
    "backgroundColor": {
      "type": "string"
    },
    "textColor": {
      "type": "string"
    },
    "fontSize": {
      "type": "string"
    }
  },
  "supports": {
    "className": false,
    "color": {
      "gradients": true,
      "link": true,
      "background": true,
      "text": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextDecoration": true,
      "__experimentalTextTransform": true
    }
  }
}
```

### Validation notes

- Must always appear as a direct child of `core/list`.
- `content` is the item text — may contain inline HTML.
- Can contain a nested `core/list` as an inner block for sub-lists.

---

## core/separator

```json
{
  "name": "core/separator",
  "attributes": {
    "opacity": {
      "type": "string",
      "default": "alpha-channel"
    },
    "backgroundColor": {
      "type": "string"
    },
    "gradient": {
      "type": "string"
    },
    "style": {
      "type": "object"
    }
  },
  "supports": {
    "anchor": true,
    "align": [
      "center",
      "wide",
      "full"
    ],
    "color": {
      "enableContrastChecker": false,
      "gradients": true
    },
    "spacing": {
      "margin": [
        "top",
        "bottom"
      ]
    }
  },
  "styles": [
    {
      "name": "default",
      "label": "Default",
      "isDefault": true
    },
    {
      "name": "wide",
      "label": "Wide Line"
    },
    {
      "name": "dots",
      "label": "Dots"
    }
  ]
}
```

### Validation notes

- Default (no attrs) renders `<hr class="wp-block-separator has-alpha-channel-opacity"/>`.
- Custom colour goes in `style.color.background` (hex). This produces both
  `background-color` and `color` inline styles on the `<hr>` — both are
  required for the validator.
- Named palette colours go in `backgroundColor` — produces
  `has-{slug}-background-color has-background` classes.
- `align: "full"` produces `alignfull` class and removes the default max-width.
- The `opacity` attribute controls the CSS opacity class:
  "css" → `has-css-opacity`, omitted → `has-alpha-channel-opacity`.

---

## core/spacer

```json
{
  "name": "core/spacer",
  "attributes": {
    "height": {
      "type": "string",
      "default": "100px"
    },
    "width": {
      "type": "string"
    },
    "style": {
      "type": "object"
    }
  },
  "supports": {
    "anchor": true
  }
}
```

### Validation notes

- `height` is required and must include a unit: "48px", "10vh", etc.
  The inline style value must match the attr value exactly.
- `width` is only relevant when the spacer is inside a flex layout.
- The wrapper is `<div style="height:{value}" aria-hidden="true" class="wp-block-spacer">`.

---

## core/quote

```json
{
  "name": "core/quote",
  "attributes": {
    "value": {
      "type": "string",
      "source": "html",
      "selector": "blockquote",
      "multiline": "p",
      "default": ""
    },
    "citation": {
      "type": "rich-text",
      "source": "rich-text",
      "selector": "cite"
    },
    "textAlign": {
      "type": "string"
    },
    "backgroundColor": {
      "type": "string"
    },
    "textColor": {
      "type": "string"
    },
    "gradient": {
      "type": "string"
    },
    "fontSize": {
      "type": "string"
    },
    "style": {
      "type": "object"
    }
  },
  "supports": {
    "align": [
      "left",
      "right",
      "wide",
      "full"
    ],
    "anchor": true,
    "color": {
      "gradients": true,
      "link": true,
      "heading": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontStyle": true,
      "__experimentalFontWeight": true,
      "__experimentalLetterSpacing": true,
      "__experimentalTextDecoration": true,
      "__experimentalTextTransform": true
    },
    "spacing": {
      "blockGap": true,
      "padding": true,
      "margin": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true
    }
  },
  "styles": [
    {
      "name": "default",
      "label": "Default",
      "isDefault": true
    },
    {
      "name": "plain",
      "label": "Plain"
    }
  ]
}
```

### Validation notes

- The quote text goes as an inner `core/paragraph` block — it is NOT a direct
  attribute. The `<blockquote>` contains the paragraph block comment and HTML.
- `citation` (not "cite") is the attribute name for the attribution text.
  It renders as a `<cite>` element inside the `<blockquote>`.
- `textAlign` aligns the quote text. Valid values: "left", "center", "right".
- Style variants: default, "large" (`is-style-large`). Set via `className`.

---
