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

### Balance
- Avoid 4-column grids (cluttered)
- Use 2-3 columns max
- For 4 items: 2x2 grid with mobile stacking
- 50/50 splits for asymmetric sections

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
- **Split**: Image one side, text other (great for products)
- **Centered**: Text center, button below (classic, versatile)
- **Full-bg**: Cover with overlay, text on top (bold, visual)

### Services/Features
- **Cards**: Boxed items with icon, title, description (common, clear)
- **Grid**: Items without borders, aligned in rows (minimal)
- **Icon-row**: Horizontal icons with labels (compact, scannable)

### About Section
- **50/50**: Text left, image right (classic)
- **Offset**: Image slightly overlapping section (dynamic)

### Testimonials
- **Cards**: Individual boxes with quote (clean)
- **Featured**: Single large quote with photo (impactful)

### Call to Action
- **Banner**: Full-width colored section (bold)
- **Boxed**: Centered content in container (contained)

### Footer
- **Multi-column**: Links organized by category (practical)
- **Minimal**: Just essential info (clean sites)

---

## 5. Color System

### Building a Palette

**Step 1: Choose vibe color** (1 primary)
- Trustworthy: #1a365d (navy), #2563eb (blue)
- Playful: #f59e0b (amber), #10b981 (emerald)
- Premium: #000000, #1c1917 (black/warm-black)
- Budget: #f97316 (orange), #eab308 (yellow)
- Tech: #0f172a (dark), #6366f1 (indigo)
- Organic: #166534 (green), #78350f (brown)

**Step 2: Add neutral** (1-2)
- Light: #f8fafc, #f1f5f9
- Dark: #1e293b, #0f172a

**Step 3: Accent** (1, optional)
- For buttons, links: Use lighter/darker version of primary

### Usage Rules
- Primary color: CTAs, key buttons
- Neutral: Backgrounds, secondary text
- Accent: Highlights, hover states
- Never use brand colors for body text on brand-colored backgrounds

---

## 6. Spacing

### Base-8 Grid
Use only: 8, 16, 24, 32, 40, 48, 56, 64, 72, 80, 96

### Context-Based
- **Trustworthy/Premium**: Airy (64-96px section padding)
- **Playful/Budget**: Tighter (40-64px section padding)
- **Tech**: Minimal (32-56px section padding)

### Hierarchy
- Section padding: 48-96px
- Card padding: 24-32px
- Element gap: 16-24px
- Button padding: 12-16px vertical, 24-32px horizontal

---

## 7. Technical Patterns

### Colors in Blocks
- AVOID hex in block attributes: `"backgroundColor":"#1a365d"` ← won't work
- USE inline styles: `style="background-color:#1a365d"`
- OR use WP palette: `"backgroundColor":"white"`, `"black"`, `"gray"`

### Responsive
- ALWAYS add `"stackOnMobile": true` to columns
- Test on mobile mentally: if it won't stack, fix it

### Buttons
- Outline buttons NEED textColor or they disappear
- Add borderColor too for safety

### Images
- Circular for icons/avatars: `border-radius:50%`
- Rounded for photos: `border-radius:16px`
- Always include alt text

---

## 8. Decision Checklist

Before publishing, verify:

- [ ] Hero matches vibe (colors, spacing feel right for business type)
- [ ] Column count appropriate (2-3 max, no 4-col)
- [ ] Mobile stacking works (all columns stackOnMobile:true)
- [ ] Buttons have colors (outline has textColor)
- [ ] Hierarchy clear (can identify focal point in each section)
- [ ] Spacing consistent (used base-8 values, similar density)
- [ ] Colors match personality (not mixed vibes)
- [ ] Mobile readable (text large enough, contrast sufficient)

---

## 9. Gutenberg Patterns

WordPress comes with built-in patterns. Use them as starting points.

### Finding Patterns
- In Gutenberg editor: Click + → Patterns
- Categories: Gallery, Banner, Text, Columns, Featured, Call to Action, etc.

### Using Patterns
1. Insert pattern from block menu
2. Adapt content (images, text, colors)
3. Keep structure, customize details

### Pattern Techniques to Borrow

**Viewport units** (from gallery patterns):
- `6vw` - dramatic large headings
- `3vw`, `2vw`, `1vw` - proportional spacers
- Responsive without media queries

**Asymmetric layouts:**
- Unequal column widths: `33.38%`, `33%`, `33.62%`
- Creates visual interest

**Category labels:**
- Small uppercase text above headings
- Adds polish: `ECOSYSTEM`, `SERVICES`, etc.

### When to Use Patterns
- Great starting point for complex sections
- Gallery layouts, hero variations, feature grids
- Adapt don't copy - match your colors/vibe

## Quick Reference

### Spacing Values
```
Section padding: 48-96px
Card padding: 24-32px  
Gap: 16-24px
Margins: 16-32px
Border radius: 8-48px
```

### Common Mistakes
- 4-column layouts
- Hex colors in block attributes
- Outline buttons without textColor
- No mobile stacking
- Mixing v clashing personalities
- All elements same size (no hierarchy)
- Too little white space

### Font Sizes
- Hero title: x-large (or custom 48-64px)
- Section heading: large
- Body: default
- Small: captions, labels
