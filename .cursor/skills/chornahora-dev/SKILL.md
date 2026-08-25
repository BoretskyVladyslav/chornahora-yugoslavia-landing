---
name: chornahora-dev
description: Technical architecture, scope of work, and strict client constraints for the Chornahora Yugoslavia book landing page migration from Weblium to WordPress.
---
# Chornahora Yugoslavia Landing - Project Guidelines

## Core Project Information
- **Client:** Publishing house "Chornahora"[cite: 3].
- **Reference Prototype:** https://6cvyd.weblium.site/ (Use for design, structure, and all texts)[cite: 3].
- **Checkout Reference:** https://rusova.chornahora.com.ua/[cite: 3].

## STRICT CLIENT CONSTRAINTS
- **NO AI GENERATION:** You are strictly forbidden from rewriting texts, changing block names, or generating/altering maps using AI. All texts and graphics must be exact copies of the original[cite: 3].

## Git
Always create a Git commit with a descriptive message after completing any major layout or logic change.

**MANDATORY:** every milestone commit MUST immediately be pushed to the remote repository (`git push origin main`). Do not leave milestone commits local-only.

## Scope of Work & Architecture
1. **Main Page (7 Screens)[cite: 3]:**
   - Template: `front-page.php`. Page title "Головна", slug `home`. Set as the static front page (`show_on_front = page`); public URL is `/`, not `/home/`.
   - Screen 1: Hero section with book cover.
   - Screen 2: Text + 2 YouTube embeds (with anchor buttons to screens 5 and 7).
   - Screen 3: Slider for historical maps.
   - Screen 4: Text block styled as a book quote.
   - Screen 5: Table of contents + map.
   - Screen 6: 3 YouTube embeds (one specific video must start at 42 seconds).
   - Screen 7: Final CTA leading to `/checkout/`.
2. **Technical Pages[cite: 3]:**
   - Payment & Delivery, Returns, Privacy Policy, Contacts. Formats must be preserved exactly as on Weblium.
   - Route via WordPress `page-{slug}.php` hierarchy. Do not invent legal copy; port Weblium markup exactly.
3. **Checkout Logic (WooCommerce or Custom REST)[cite: 3]:**
   - Dedicated page `/checkout/` (`page-checkout.php`), 2-column layout matching Rusova.
   - Required Fields ONLY: Full Name, Phone (must have +380 mask), Email.
   - Delivery: Nova Poshta API only. Key: `b1c8fee45753bde5092988529e9f305b`. Must include city and branch/postomat selection.
   - Payment: WayForPay (online) and Cash on Delivery.
   - After COD: redirect to `/thank-you/` (`page-thank-you.php`). WayForPay returnUrl also lands there.
4. **Integrations[cite: 3]:**
   - Automatic order logging to Google Sheets.
   - Instant email notifications to `chornagorabook@gmail.com`.

## Page setup and template routing
Pages are created on theme activation (`after_switch_theme`) and ensured on `init` via `chornahora_pages_version` in `functions.php`. Skip insert if the slug already exists; always re-apply the static front page options.

- `/` — Головна (slug `home`) — `front-page.php`
- `/checkout/` — Оформлення замовлення — `page-checkout.php`
- `/thank-you/` — Замовлення отримано — `page-thank-you.php`
- `/oplata-ta-dostavka/` — Оплата та доставка — `page-oplata-ta-dostavka.php`
- `/povernennya/` — Повернення — `page-povernennya.php`
- `/politika-konfidenciynosti/` — Політика конфіденційності — `page-politika-konfidenciynosti.php`
- `/kontakty/` — Контакти — `page-kontakty.php`

Landing CTAs ("ЗАМОВИТИ КНИГУ") must link to `/checkout/`.

## Coding Standards
- Write clean, vanilla JS and optimized CSS (or Tailwind).
- Ensure high performance, lazy loading for YouTube iframes, and strictly follow WordPress security best practices (nonces, sanitization).
