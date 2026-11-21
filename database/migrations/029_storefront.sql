-- Optional public storefront per shop (slug + enable flag).

ALTER TABLE fashion_tenants
    ADD COLUMN storefront_slug VARCHAR(80) NULL,
    ADD COLUMN storefront_enabled TINYINT NOT NULL DEFAULT 0;

CREATE UNIQUE INDEX uq_fashion_tenants_storefront_slug
    ON fashion_tenants (storefront_slug);
