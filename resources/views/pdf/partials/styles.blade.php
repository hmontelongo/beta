@php
    $brandColorLight = $brandColor . '20';
    $brandColorMedium = $brandColor . '40';
@endphp

:root {
    --brand-color: {{ $brandColor }};
    --brand-light: {{ $brandColorLight }};
    --brand-medium: {{ $brandColorMedium }};
}

@page {
    size: letter;
    margin: 0.5in;
}

* {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 10pt;
    line-height: 1.5;
    color: #171717;
}

/* ========================================
   COVER PAGE
   ======================================== */

.cover-page {
    height: 9.25in;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    page-break-after: always;
}

.cover-header {
    text-align: center;
    padding-top: 1in;
}

.agent-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--brand-color);
    margin: 0 auto 16px;
    display: block;
}

.agent-avatar-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--brand-color), var(--brand-medium));
    color: white;
    font-size: 36pt;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.agent-name {
    font-size: 20pt;
    font-weight: 700;
    color: #171717;
    margin-bottom: 4px;
}

.agent-tagline {
    font-size: 11pt;
    color: #737373;
}

.cover-content {
    text-align: center;
    padding: 0 0.5in;
}

.collection-label {
    font-size: 9pt;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--brand-color);
    margin-bottom: 8px;
}

.collection-title {
    font-size: 32pt;
    font-weight: 700;
    color: #171717;
    line-height: 1.2;
    margin-bottom: 24px;
}

.collection-meta {
    display: flex;
    justify-content: center;
    gap: 24px;
    font-size: 11pt;
    color: #525252;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.cover-footer {
    text-align: center;
    padding-bottom: 0.5in;
}

.cover-contact {
    display: flex;
    justify-content: center;
    gap: 24px;
    font-size: 10pt;
    color: #525252;
}

/* ========================================
   PROPERTY CARD
   ======================================== */

.property-card {
    break-inside: avoid;
    margin-bottom: 20px;
}

.property-card + .property-card {
    margin-top: 24px;
}

.property-header {
    position: relative;
    margin-bottom: 12px;
    break-inside: avoid;
}

.property-number {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--brand-color), var(--brand-medium));
    color: white;
    font-size: 16pt;
    font-weight: 700;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.property-main-image {
    width: 100%;
    height: 2.2in;
    object-fit: cover;
    border-radius: 8px;
    display: block;
}

.property-thumbnails {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4px;
    margin-top: 4px;
}

.property-thumb {
    height: 0.5in;
    object-fit: cover;
    border-radius: 4px;
    width: 100%;
}

.property-price-overlay {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.property-price {
    color: white;
    text-shadow: 0 1px 4px rgba(0,0,0,0.5);
}

.price-amount {
    font-size: 22pt;
    font-weight: 700;
}

.price-currency {
    font-size: 12pt;
    font-weight: 400;
    opacity: 0.9;
}

.price-per-m2 {
    font-size: 9pt;
    opacity: 0.8;
    margin-top: 2px;
}

.property-type-badge {
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 9pt;
    font-weight: 600;
    text-transform: uppercase;
    color: white;
}

.badge-sale { background: var(--brand-color); }
.badge-rent { background: #10b981; }

/* Property Info */
.property-info {
    padding: 12px 0;
    break-inside: avoid;
}

.property-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.property-type {
    font-size: 14pt;
    font-weight: 700;
    color: #171717;
}

.property-condition {
    display: inline-block;
    padding: 2px 10px;
    background: var(--brand-light);
    color: var(--brand-color);
    border-radius: 999px;
    font-size: 9pt;
    font-weight: 500;
    margin-left: 8px;
}

.property-address {
    font-size: 10pt;
    color: #525252;
    display: flex;
    align-items: center;
    gap: 4px;
}

.map-link {
    font-size: 9pt;
    color: #525252;
    text-decoration: none;
    padding: 4px 10px;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
}

/* Specs Grid */
.specs-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 6px;
    margin: 12px 0;
    break-inside: avoid;
}

.spec-item {
    text-align: center;
    padding: 10px 6px;
    background: #f5f5f5;
    border-radius: 8px;
}

.spec-value {
    font-size: 16pt;
    font-weight: 700;
    color: #171717;
}

.spec-label {
    font-size: 7pt;
    color: #737373;
    margin-top: 2px;
    text-transform: uppercase;
}

/* Target Audience */
.target-audience {
    margin: 10px 0;
    break-inside: avoid;
}

.target-label {
    font-size: 8pt;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #737373;
    margin-bottom: 6px;
}

.audience-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.audience-tag {
    padding: 4px 12px;
    background: var(--brand-light);
    color: var(--brand-color);
    border-radius: 999px;
    font-size: 9pt;
    font-weight: 500;
}

/* Section Headers */
.section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 9pt;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #171717;
    margin: 14px 0 8px;
    padding-top: 10px;
    border-top: 1px solid #e5e5e5;
}

.section-header::before {
    content: '';
    width: 3px;
    height: 14px;
    background: var(--brand-color);
    border-radius: 2px;
}

/* Description */
.property-description {
    font-size: 9pt;
    line-height: 1.5;
    color: #404040;
    break-inside: avoid;
}

.description-truncated {
    font-size: 8pt;
    color: #888;
    font-style: italic;
    margin-top: 6px;
}

/* Amenities */
.amenities-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    break-inside: avoid;
}

.amenity-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: #f5f5f5;
    border-radius: 999px;
    font-size: 8pt;
    color: #525252;
}

.amenity-check {
    color: var(--brand-color);
    font-weight: 600;
}

.amenity-more {
    padding: 4px 10px;
    background: var(--brand-light);
    color: var(--brand-color);
    border-radius: 999px;
    font-size: 8pt;
    font-weight: 500;
}

/* ========================================
   CONTACT FOOTER
   ======================================== */

.contact-footer {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 2px solid var(--brand-color);
    text-align: center;
}

.footer-agent {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 16px;
}

.footer-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}

.footer-avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--brand-color);
    color: white;
    font-size: 18pt;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.footer-agent-info {
    text-align: left;
}

.footer-agent-name {
    font-size: 12pt;
    font-weight: 600;
    color: #171717;
}

.footer-agent-contact {
    font-size: 10pt;
    color: #525252;
}

.footer-cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    background: #25D366;
    color: white;
    font-size: 11pt;
    font-weight: 600;
    border-radius: 999px;
    text-decoration: none;
    margin-bottom: 16px;
}

.footer-timestamp {
    font-size: 8pt;
    color: #a3a3a3;
}

/* Utility */
.text-center { text-align: center; }
.mt-2 { margin-top: 8px; }
.mt-4 { margin-top: 16px; }
