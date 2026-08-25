# Chornahora Yugoslavia Landing

WordPress landing page for the Chornahora publishing house book *Yugoslavia*. Custom theme plus checkout, migrated from the Weblium prototype.

## Stack

- WordPress
- WooCommerce
- Custom theme: `wp-content/themes/chornahora-theme/`

## Landing page (7 sections)

1. **Hero** — book cover.
2. **Intro** — copy plus two YouTube embeds, with anchors to sections 5 and 7.
3. **Maps slider** — historical maps.
4. **Quote** — text block styled as a book quote.
5. **Contents** — table of contents plus map.
6. **Videos** — three YouTube embeds (one starts at 0:42).
7. **CTA** — final call to action into checkout.

Legal/info pages: Payment & Delivery, Returns, Privacy Policy, Contacts.

## Checkout

Custom flow (WooCommerce or REST):

- Fields: full name, phone (`+380` mask), email
- Delivery: Nova Poshta (city + branch/postomat)
- Payment: WayForPay (online) or cash on delivery
- After order: Google Sheets log and email notification
