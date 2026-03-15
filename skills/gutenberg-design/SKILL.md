---
name: gutenberg-design
description: Create professional, beautiful websites using Gutenberg blocks. Use design thinking to match the page to its context (business type, goals, audience, personality). Apply visual principles and component patterns appropriately.
---

# Gutenberg Design Guide

## 1. Design Thinking

Before touching blocks, answer these questions:

1. **What type of business?** (B2B, B2C, SaaS, portfolio, nonprofit, local business)
2. **What's the primary goal?** (Convert visitors, build trust, inform, entertain)
3. **Who is the audience?** (Professionals, families, youth, seniors, luxury seekers)
4. **What personality?** (Trustworthy, playful, premium, budget-friendly, tech, organic)
5. **Any brand guidelines?** (Colors, fonts, existing assets)
6. **What other pages exist on this site?** Check before building — vary the section sequence deliberately.

Your answers determine everything else. A pharmacy needs different treatment than a toy store.

---

## 2. Personality Vibe

The vibe shapes all design decisions:

| Vibe | Colors | Spacing | Typography | Shapes |
|------|--------|---------|------------|--------|
| **Trustworthy** | Blues, navy, structured | Conservative, aligned | Clean sans-serif | Sharp, minimal |
| **Playful** | Bright, varied | Dynamic, asymmetric | Friendly, rounded | Rounded, bold |
| **Premium** | Black, gold, muted | Airy, generous | Serif headings | Minimal, elegant |
| **Budget-Friendly** | Warm, orange/yellow | Tight, friendly | Casual | Rounded, soft |
| **Tech/Modern** | Dark, gradients | Minimal, sharp | Mono or geometric | Sharp edges |
| **Organic** | Earth tones, green | Natural, varied | Warm serif | Organic curves |

Choose ONE vibe. Mix sparingly.

---

## 3. Visual Principles

### Scale
- Maximum **3 font sizes** per section
- Important = bigger. Supporting = smaller.
- Headings: large/x-large. Body: default.

### Hierarchy
- Clear order: Title → Subtitle → CTA → Body
- De-emphasize to emphasize (make things smaller/subtle to make important things pop)
- Use ONE focal point per section

### Contrast
- Dark bg → light text. Light bg → dark text.
- Don't use grey text on colored backgrounds (hard to read)
- Use color sparingly for impact, not everywhere

### Gestalt
- Related items = grouped together (proximity)
- Same padding, border-radius = same level of importance
- Cards group related content visually

---

## 4. Component Variants

### Hero Section
- **Split**: Image one side, text other — use `two-column` with image left and text right
- **Centered**: Text center, button below — use `cover` with `title`, `subtitle`, `buttons`
- **Full-bg**: Cover with overlay, text on top — use `cover` with `image` and `overlayHex`

### Services / Features
- **Cards**: Use `three-column` with `icon`, `heading`, `text` per column
- **Grid**: Use multiple `block` sections with `core/columns` and custom widths
- **Icon-row**: Use `three-column` with emoji/SVG icons and short labels

### About Section
- **50/50**: Use `two-column` with `image` left and `heading`+`text` right
- **Reversed**: Use `two-column` with `reverse: true`

### Testimonials
- **Cards**: Use `three-column` with quote text and attribution per column
- **Featured**: Use `two-column` with large quote left, author image right

### Call to Action
- **Banner**: Use `cover` with dark `overlayHex`, centered `title` and `buttons`
- **Boxed**: Use `block` with `core/group`, centered content and a background color

### Stats Band
- Use `three-column` or `block` with `core/columns`, each column showing a large number heading and label

### Footer
- **Multi-column**: Use `footer` with `columns` array (type: about, links, social, contact)
- **Minimal**: Use `footer` with a single text column and copyright only

---

## 5. Color System

### Building a Palette

**Step 1: Choose vibe color** (1 primary)
- Trustworthy: `#1a365d` (navy), `#2563eb` (blue)
- Playful: `#f59e0b` (amber), `#10b981` (emerald)
- Premium: `#000000`, `#1c1917` (black/warm-black)
- Budget: `#f97316` (orange), `#eab308` (yellow)
- Tech: `#0f172a` (dark), `#6366f1` (indigo)
- Organic: `#166534` (green), `#78350f` (brown)

**Step 2: Add neutral** (1-2)
- Light: `#f8fafc`, `#f1f5f9`
- Dark: `#1e293b`, `#0f172a`

**Step 3: Accent** (1, optional)
- For buttons, links: lighter/darker version of primary

### Usage Rules
- Primary color: CTAs, key buttons, hero overlays
- Neutral: Backgrounds, secondary text
- Accent: Highlights, hover states
- Never use brand colors for body text on brand-colored backgrounds

---

## 6. Spacing

### Base-8 Grid
Use only: 8, 16, 24, 32, 40, 48, 56, 64, 72, 80, 96

### Context-Based
- **Trustworthy/Premium**: Airy (64–96px section padding)
- **Playful/Budget**: Tighter (40–64px section padding)
- **Tech**: Minimal (32–56px section padding)

### Hierarchy
- Section padding: 48–96px
- Card padding: 24–32px
- Element gap: 16–24px
- Button padding: 12–16px vertical, 24–32px horizontal

---

## 7. Colors in Blocks — Correct Usage

> **This section overrides any older advice about avoiding hex in block attributes.**
> The renderer handles color placement correctly — just pass values as shown below.

### Always use hex values for custom colors

Pass hex directly in section/block data. The renderer puts it in the right place automatically:

```json
{ "backgroundColor": "#1a365d" }   // ✓ correct — renderer uses style.color.background
{ "textColor": "#ffffff" }          // ✓ correct — renderer uses style.color.text
{ "overlayHex": "#000000" }         // ✓ correct — for cover/hero overlay color
```

**Never try to write inline styles yourself** — the renderer generates them from your values.

### Named palette slugs — use with caution

Named slugs like `"backgroundColor": "white"` or `"textColor": "black"` only work if the active theme registers that exact palette name. They will cause block validation errors on themes that don't. **Use hex values to be safe on any theme.**

### Cover / Hero overlay

Use `overlayHex` (not `overlayColor`) for the overlay tint:

```json
{
  "type": "cover",
  "image": "https://...",
  "overlayHex": "#1a365d",
  "overlay_opacity": 0.7
}
```

### Buttons

Buttons accept hex or palette slugs for `backgroundColor` and `textColor`. The renderer
differentiates automatically: hex → inline style, slug → palette class.

```json
{ "block": "core/button", "text": "Get Started", "url": "/contact",
  "backgroundColor": "#2563eb", "textColor": "#ffffff" }
```

Outline buttons **must** include `textColor` or the text becomes invisible:

```json
{ "block": "core/button", "text": "Learn More", "url": "/about",
  "variant": "outline", "textColor": "#2563eb" }
```

---

## 8. Responsive

- ALWAYS add `"isStackedOnMobile": true` to `core/columns` blocks
- For section-level columns (two-column, three-column), the renderer handles stacking automatically
- Test on mobile mentally: if it won't stack, fix it

---

## 9. Page Structure Patterns

**Rule: Never use the same section sequence for two pages on the same site.**

Vary the section sequence deliberately based on page purpose. Use these as starting points — adapt freely.

### Landing Page
```
cover (full-bg hero, overlayHex, title + subtitle + buttons)
three-column (features/benefits with icons)
two-column (proof: image left, benefits text right)
cover (CTA band: dark overlay, centered title + single button)
footer
```

### Services Page
```
cover (split feel: title left-aligned, subtitle, one button)
three-column (service cards with heading + text)
two-column (deep dive: text left, image right)
three-column (why us: stats or testimonials)
cover (CTA band)
footer
```

### About Page
```
cover (team photo background, overlayHex, company tagline)
two-column (story: text left, founder image right)
three-column (values: icon + heading + short text)
two-column (social proof: quote left, metrics right, reverse: true)
footer
```

### Product / Portfolio Page
```
cover (product hero, dark overlay, product name + one-liner + buy button)
two-column (feature 1: image left, description right)
two-column (feature 2: description left, image right — reverse: true)
three-column (specs or use cases)
cover (CTA band)
footer
```

### Contact / Simple Page
```
cover (minimal: title + subtitle, no image, solid background color via overlayHex)
block (core/group with contact form or details, centered, contained width)
two-column (optional: map left, address/hours right)
footer
```

### Blog / Archive Page
```
cover (minimal hero, category title)
block (core/columns with post cards)
block (core/buttons — load more / pagination)
footer
```

---

## 10. Section Schema Reference

This is the exact JSON structure each section type accepts. Use this as your reference when calling `cdw/build-page`.

### `cover` — Full-width hero or CTA band

```json
{
  "type": "cover",
  "image": "https://...",        // optional background image URL
  "image_id": 42,                // optional WP attachment ID
  "overlayHex": "#000000",       // overlay tint color (hex, default #000000)
  "overlay_opacity": 0.6,        // 0.0–1.0, controls dim intensity
  "minHeight": 600,              // px, default 600
  "title": "Heading text",       // large h1
  "subtitle": "Supporting text", // paragraph below title
  "content": "Body text",        // additional paragraph
  "blocks": [ ... ],             // explicit inner blocks (overrides title/subtitle)
  "buttons": [
    { "text": "Primary CTA", "url": "/page", "style": "fill" },
    { "text": "Secondary", "url": "/other", "style": "outline" }
  ]
}
```

### `two-column` — 50/50 split section

```json
{
  "type": "two-column",
  "title": "Section heading",     // optional — renders as group ABOVE columns
  "subtitle": "Label text",       // optional — small text above title
  "reverse": false,               // true = text left, image right
  "backgroundColor": "#f8fafc",  // hex or palette slug
  "left": {
    "image": "https://...",       // if this column is an image
    "alt": "Description",
    "blocks": [ ... ]             // OR explicit blocks
  },
  "right": {
    "heading": "Column heading",
    "text": "Column body text",
    "paragraphs": ["Extra paragraph"],
    "blocks": [ ... ]             // OR explicit blocks
  }
}
```

### `three-column` — Feature / card grid

```json
{
  "type": "three-column",
  "title": "Section heading",     // optional — renders as group ABOVE columns
  "subtitle": "LABEL TEXT",       // optional — small uppercase label
  "backgroundColor": "#ffffff",
  "columns": [
    {
      "icon": "🚀",               // emoji or text icon
      "heading": "Feature title",
      "text": "Feature description.",
      "blocks": [ ... ]           // OR explicit blocks override icon/heading/text
    },
    { "icon": "⚡", "heading": "Speed", "text": "Fast and reliable." },
    { "icon": "🔒", "heading": "Secure", "text": "Enterprise-grade security." }
  ]
}
```

### `footer` — Page footer

```json
{
  "type": "footer",
  "background_color": "#1a1a1a",
  "text_color": "#cccccc",
  "heading_color": "#ffffff",
  "muted_color": "#888888",
  "border_color": "#333333",
  "copyright": "© 2025 Company Name. All rights reserved.",
  "columns": [
    { "type": "about", "title": "Company", "text": "We build great things." },
    { "type": "links", "title": "Pages",
      "items": ["Home", "About", "Services", "Contact"],
      "links": ["/", "/about", "/services", "/contact"] },
    { "type": "social", "title": "Follow Us",
      "networks": ["twitter", "linkedin"],
      "urls": ["https://twitter.com/...", "https://linkedin.com/..."] },
    { "type": "contact", "title": "Contact",
      "email": "hello@example.com", "phone": "+1 555 000 0000" }
  ]
}
```

### `block` — Any single Gutenberg block

```json
{ "type": "block", "block": "core/paragraph", "content": "Text", "align": "center" }
{ "type": "block", "block": "core/heading", "content": "Title", "level": 2, "textAlign": "center" }
{ "type": "block", "block": "core/image", "url": "https://...", "alt": "Description", "sizeSlug": "full" }
{ "type": "block", "block": "core/spacer", "height": "48px" }
{ "type": "block", "block": "core/separator" }
{ "type": "block", "block": "core/buttons",
  "layout": { "type": "flex", "justifyContent": "center" },
  "buttons": [
    { "text": "CTA", "url": "/page", "backgroundColor": "#2563eb", "textColor": "#ffffff" }
  ]
}
{ "type": "block", "block": "core/group", "align": "full",
  "backgroundColor": "#f1f5f9",
  "style": { "spacing": { "padding": { "top": "64px", "bottom": "64px", "left": "40px", "right": "40px" } } },
  "children": [ ... ]
}
{ "type": "block", "block": "core/columns",
  "align": "full",
  "isStackedOnMobile": true,
  "children": [
    { "block": "core/column", "width": "33.33%", "children": [ ... ] },
    { "block": "core/column", "width": "33.33%", "children": [ ... ] },
    { "block": "core/column", "width": "33.33%", "children": [ ... ] }
  ]
}
{ "type": "block", "block": "core/quote", "content": "Quote text.", "cite": "— Author Name" }
{ "type": "block", "block": "core/list",
  "items": ["First item", "Second item", "Third item"] }
{ "type": "block", "block": "core/list", "ordered": true,
  "items": ["Step one", "Step two", "Step three"] }
```

#### Valid block names
`core/paragraph` `core/heading` `core/image` `core/cover` `core/group`
`core/columns` `core/column` `core/buttons` `core/button` `core/spacer`
`core/separator` `core/quote` `core/code` `core/list` `core/video`
`core/audio` `core/file` `core/html` `core/embed`

---

## 11. Decision Checklist

Before submitting the sections array, verify:

- [ ] Section sequence is unique — not the same pattern as other pages on this site
- [ ] Hero matches vibe (colors, spacing, personality feel correct for business type)
- [ ] `overlayHex` used for cover/hero — NOT `overlayColor`
- [ ] All colors are hex values (`#rrggbb`) — not named palette slugs
- [ ] Outline buttons have `textColor` set
- [ ] Column count appropriate (2–3 max, no 4-col)
- [ ] `isStackedOnMobile: true` on any explicit `core/columns` blocks
- [ ] Buttons have colors (never leave default unstyled)
- [ ] Hierarchy clear (can identify focal point in each section)
- [ ] Spacing consistent (base-8 values: 8/16/24/32/40/48/56/64/72/80/96px)
- [ ] Colors match one vibe (not mixed personalities)
- [ ] Every page on the site has a consistent footer color scheme

---

## 12. Common Mistakes

| Mistake | Fix |
|---|---|
| Using `"overlayColor": "black"` | Use `"overlayHex": "#000000"` |
| Outline button with no `textColor` | Always add `"textColor": "#xxxxxx"` |
| Same section sequence on every page | Follow page structure patterns in section 9 |
| `"backgroundColor": "white"` (palette slug) | Use `"backgroundColor": "#ffffff"` |
| `"stackOnMobile": true` | Correct key is `"isStackedOnMobile": true` |
| `has-white-color` class on cover inner text | Use `"style":{"color":{"text":"#ffffff"}}` |
| 4-column layout | Max 3 columns — split into rows instead |
| All elements same size | Vary font sizes: hero title x-large, body default |
| Too little white space | Section padding minimum 48px for any vibe |
| Mixing vibe colors | Pick ONE vibe, stay consistent across all pages |
