# Private file delivery and deployment

The web root must be the repository's `public` directory. Never expose the repository root, `storage/app`, backup roots, or temporary export directories through an alias or symlink.

## File classification

| File family | Classification | Delivery and ownership |
| --- | --- | --- |
| `images/logo`, `images/biller` | Public brand asset | Application/company invoice branding; public URLs remain supported. |
| `images/product` including resized variants | Public product asset | Catalogue/POS product photographs. |
| `images/brand`, `images/category` including icons/resized variants | Public brand/product asset | Brand and catalogue presentation. |
| Bundled icons, placeholders, flags, CSS/JS images | Public application asset | Static presentation, not business records. |
| `images/employee`, `images/sale_agent` | Private user/HR document | Active employee in the current company, HR read permission, warehouse branch grant when assigned. |
| `images/supplier` | Private company document | Generic image on a supplier record, without an explicit public-logo contract; active supplier and supplier read permission. |
| `documents/notification` | Private user/business document | Current company membership; recipient's personal read permission or `all_notification`; branch grant when recorded. New sends validate sender/recipient membership and branch grants. |
| `documents/production` | Private operations document | Production's actual warehouse establishes company and branch; `manufacturing.read` required. |
| `documents/sale`, `purchase`, `sale_return`, `purchase_return`, `quotation`, `expense`, `delivery`, `transfer`, `adjustment`, `add-payment` | Private company document | Live referencing document, existing module read permission, current company membership and authorized warehouse branches; delivery/payment parents are checked. |
| Accounting/GST exports and generated downloads | Temporary private export | Existing authorized export endpoints. Do not publish export files in the web root. |
| Backup/recovery archives and receipts | Private recovery data | Existing backup service enforces private roots and private off-site visibility. Never expose through HTTP aliases. |

No customer identity upload family was found in the audited controllers. A newly introduced identity/document upload must use private storage and an owning-record authorization check.

New notification, production, HR and supplier files use unpredictable names under `storage/app/private/files/<family>`. Keep this directory writable by PHP and unreadable through HTTP. Historical files remain in their existing locations and are read through `/secure-documents/<family>/<filename>` after authorization. Retained sale-agent portraits in `images/sale_agent` are supported by the employee endpoint. Files without a live reference, inactive private image owners, deleted commercial documents, traversal paths and symlinks that redirect file resolution are refused. Business documents download as attachments with private cache headers and `nosniff`.

Legacy notifications without a company field are readable only when sender and recipient share exactly one company. Rows with conflicting or multiple possible owners remain unavailable until an operator reviews ownership. Do not assign them to whichever company happens to be selected.

## Apache

Keep `public/.htaccess` enabled (`AllowOverride` must permit its rewrite rules). The checked-in rules deny direct requests to private document folders and private HR/supplier image folders before the front controller. PHP authorization is independent of this web-server rule. A server that ignores `.htaccess` must install equivalent deny rules in its virtual host.

## Nginx

Place these locations in the application server block. Adapt `root` and the existing PHP handler to the deployment. Do not add a `^~ /documents/` or `^~ /images/` location that bypasses the regex deny rules. The storage URL may expose only `storage/app/public`.

```nginx
root /srv/zolo-erp-pro/public;

location ~* ^/documents/(sale|purchase|sale_return|purchase_return|quotation|expense|delivery|transfer|adjustment|add-payment|notification|production)(/|$) {
    return 403;
}

location ~* ^/images/(employee|sale_agent|supplier)(/|$) {
    return 403;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Keep the normal PHP handler restricted to legitimate PHP entry points. Keep backups outside this root. Run `nginx -t` before reloading. Apache and Nginx deployment smoke tests must confirm:

1. Direct private URLs return 403 even when the physical file exists.
2. Anonymous secure downloads redirect to login or return 401.
3. An authorized member can read their company file through the secure route.
4. Foreign company/branch, deleted/unreferenced file and traversal requests fail.
5. Public company logos and catalogue images continue to render.

Framework tests verify authorization and path containment. They cannot prove that a deployed web server honors its virtual-host configuration; record these deployment checks on the actual server.
