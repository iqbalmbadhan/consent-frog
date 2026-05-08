=== ConsentForge ===
Contributors: consentforge
Tags: gdpr, cookie consent, privacy, digital omnibus, gpc, dsar, consent mode
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The first WordPress consent plugin built for the post-Digital Omnibus era. Opt-out analytics, legally binding GPC, DSAR automation, and full 2026 EU GDPR compliance.

== Description ==

ConsentForge is the next-generation privacy consent platform for WordPress, built from the ground up for the EU Digital Omnibus directive (in force February 2026) and the updated GDPR framework it introduces.

Most consent plugins were designed for the 2018 GDPR world. ConsentForge is designed for 2026 and beyond.

**Digital Omnibus Consent Model**

The Digital Omnibus directive fundamentally changes how consent works for analytics and performance cookies. ConsentForge implements the correct legal model:

* Analytics and performance cookies: **opt-out** (users are opted in by default, must actively opt out)
* Marketing and advertising cookies: **opt-in** (explicit consent required before any tracking)
* Strictly necessary cookies: **exempt** (no consent required)

ConsentForge makes it simple to configure and audit this model correctly, with jurisdiction-aware rules so the right model is applied for each visitor's location.

**Legally Binding Global Privacy Control (GPC)**

Global Privacy Control is now a legally binding signal under multiple EU member state implementations of the Digital Omnibus directive. ConsentForge:

* Detects the `Sec-GPC: 1` HTTP header and the `navigator.globalPrivacyControl` browser property
* Automatically applies opt-out for all non-essential categories when GPC is detected
* Logs GPC detection in every consent receipt for audit purposes
* Does not prompt users who have already signalled their preference via GPC

**Consent Receipts and Audit Trail**

Every consent event generates a cryptographically chained receipt stored in your database:

* Each receipt is hashed and linked to the previous receipt (blockchain-style chain of custody)
* Receipts record the exact banner version, plugin version, categories accepted/rejected, GPC signal, IP country, and timestamp
* Export receipts in JSON format for regulatory audits
* Receipts satisfy the GDPR Article 7(1) burden-of-proof requirement

**Consent Mode v2 Integration**

ConsentForge emits the correct Google Consent Mode v2 signals automatically, mapped to the Digital Omnibus category model. No manual configuration required.

**Data Subject Access Request (DSAR) Automation**

ConsentForge includes a full DSAR workflow engine:

* Embeddable DSAR request form (shortcode and block)
* Automated email verification of the requester's identity
* Data discovery across WordPress users, WooCommerce orders, comments, and consent logs
* Exportable data packages in machine-readable JSON format
* 30-day deadline tracker with admin notifications
* Erasure workflow with selective deletion safeguards

**Cookie Scanner**

* Automatic headless scan of your site's pages on activation and on a daily schedule
* Detects cookies, localStorage entries, tracking pixels, and third-party scripts
* Auto-categorises discovered cookies against a built-in database of 1,000+ known trackers
* Flags uncategorised cookies for manual review in the admin dashboard

**Banner Designer**

* Drag-and-drop banner layout designer
* Supports bottom bar, corner popup, center modal, and top bar positions
* Full colour and typography control
* Live preview in the WordPress admin
* Accessible by default: WCAG 2.1 AA compliant, keyboard navigable, screen-reader tested

**AI Act Disclosure (Optional)**

Enable the optional AI Act module to display disclosures when AI-generated content or AI-assisted profiling is in use on your site — required for certain high-risk AI applications under the EU AI Act.

**Developer-Friendly**

* Full REST API for reading and writing consent records
* JavaScript SDK for headless or decoupled frontends
* WP-CLI commands for bulk operations and diagnostics
* 40+ action and filter hooks
* PSR-4 autoloaded codebase, PHP 8.1+ typed properties and enums

== Installation ==

1. Upload the `consentforge` folder to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **ConsentForge > Setup Wizard** to complete the initial configuration in under five minutes.
4. Run the cookie scanner from **ConsentForge > Cookie Scanner** to automatically discover and categorise cookies on your site.
5. Customise your consent banner from **ConsentForge > Banner Designer** and publish when ready.

== Frequently Asked Questions ==

= What is the Digital Omnibus directive and why does it matter for my consent banner? =

The EU Digital Omnibus directive (formally the Omnibus II / Digital Package directive) entered into force in February 2026 and was transposed by member states through mid-2026. It amends the ePrivacy Directive to introduce a tiered consent model: analytics and performance cookies no longer require prior opt-in consent — users must instead be given a clear opt-out mechanism. Marketing and advertising cookies still require explicit opt-in. This is a significant change from how most WordPress consent plugins currently behave. ConsentForge implements this model correctly out of the box.

= Is GPC (Global Privacy Control) legally binding in the EU? =

Yes, under Digital Omnibus implementing legislation in several EU member states, a GPC signal must be treated as a valid opt-out of non-essential data processing. ConsentForge automatically detects GPC signals via both the HTTP header (`Sec-GPC: 1`) and the JavaScript property (`navigator.globalPrivacyControl`), and applies the appropriate consent restrictions without displaying a banner to those users. Every GPC-based consent decision is recorded in the audit trail.

= I am already using another consent plugin (Complianz, CookieYes, Cookiebot, etc.). Can I migrate? =

Yes. ConsentForge includes an import wizard under **ConsentForge > Tools > Import** that can read configuration exports from Complianz, CookieYes, and Cookiebot. Cookie categories, custom scripts, and geo-rules are mapped to the ConsentForge data model automatically. You should deactivate your previous plugin only after verifying the import and testing your banner. ConsentForge will display an admin notice if it detects a competing consent plugin is active at the same time.

= My analytics are opt-out under Digital Omnibus. Won't that hurt my data quality? =

In practice, opt-out analytics tends to produce more accurate data than opt-in analytics, because the consent rate for analytics under an opt-in model is typically 40–70%, introducing significant sampling bias. With opt-out analytics, you collect data from the vast majority of visitors (minus those who actively opt out or signal GPC), giving you a much more representative dataset. ConsentForge still blocks analytics scripts for users who do opt out or signal GPC, so you remain fully compliant.

= What data does ConsentForge store, and how do I handle DSAR requests for it? =

ConsentForge stores consent logs (hashed visitor identifiers, consent decisions, timestamps, and receipt hashes), cookie scan results, and DSAR request records. No plaintext IP addresses or personal names are stored in consent logs — visitors are identified by a salted hash. When you receive a DSAR, you can use **ConsentForge > DSAR Manager** to look up records by email address. ConsentForge will search consent logs, WordPress user accounts, WooCommerce orders, and comments, and compile a downloadable data package. Erasure requests are handled through the same interface, with safeguards to prevent deletion of records required for legal compliance purposes.

== Changelog ==

= 1.0.0 =
* Initial release.
* Digital Omnibus opt-out model for analytics and performance cookies; opt-in model for marketing.
* Legally binding GPC detection via HTTP header and JavaScript property.
* Cryptographically chained consent receipts with JSON export.
* Google Consent Mode v2 signal emission.
* Automated cookie scanner with 1,000+ tracker database.
* DSAR workflow engine with email verification and data export.
* Banner designer with four layout positions and live preview.
* AI Act disclosure module (optional).
* REST API, JavaScript SDK, and WP-CLI commands.
* Import wizard for Complianz, CookieYes, and Cookiebot.
