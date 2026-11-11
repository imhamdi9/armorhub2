# Partners and sellers: signing up, documents, verification

There are three kinds of account, chosen on the sign-up page (`/signup`):

| | Who | Joins | Reviewed by | Gets |
|---|---|---|---|---|
| **Customer** | Buys products and services | a store | nobody (instant) | the `customer` role |
| **Business partner** | Does the service work (installs, repairs, salons) | a store | platform admins **or** that store's owner | the `partner-manager` role, a partner profile, quote requests from nearby customers |
| **Seller** | A brand or company that sells its own products | a marketplace (e.g. "ArmorHub Egypt") | platform admins only | the `seller` role, its own company, its own products |

## How an application works

1. The business fills the form and uploads its documents. Nothing exists yet: no account, no role, no profile.
2. Signing in before approval only explains the status ("under review" / "not approved"), and only to someone who knows
   the password they chose.
3. Reviewers are notified. They open each document, tick **I opened and checked every document**, and approve or reject
   (with a reason). Approval is blocked until the required documents are all on file and the box is ticked.
4. On approval the account, role and profile are created in one step. The applicant signs in with the password they chose.
5. A rejected business can apply again.

## Documents

| Document | Partner | Seller |
|---|---|---|
| Owner ID or passport | required | required |
| Commercial registration | required | required |
| Tax / VAT certificate | – | required |
| Professional licence / brand or trade licence | optional | optional |

The commercial registration number (and, for sellers, the tax number, checked against the country's format) are also collected.
Files: PDF, JPG or PNG, 5 MB each. They are judged by their **content**, not their name, so a renamed script is refused.

### Where documents are kept

- Always on a **private** disk, under random names. There is no public URL; a document can only be opened by a signed-in
  reviewer through the API, and every view is written to the audit log.
- By default the server's private disk (`DOCUMENTS_DISK=local`). **Hosts that reset their disk (Render free) lose these files**:
  for anything real, create a private S3-compatible bucket (Cloudflare R2, AWS S3, ...) and set
  `DOCUMENTS_DISK=s3`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` (`auto` for R2), `AWS_BUCKET`,
  and for R2/others `AWS_ENDPOINT` and `AWS_USE_PATH_STYLE_ENDPOINT=true`. Platform → Admin → Security warns until you do.
- `php artisan applications:prune-documents --days=90` deletes the documents of rejected applications older than 90 days.
  Run it from a scheduler; approved businesses' documents are kept.

## What a seller can do

- List products (Products). Ownership, industry and country come from the marketplace, never from the form. A seller sees and edits only its
  own products and prices; store owners and other sellers cannot touch them.
- Customers see a seller's products in the marketplace's stores ("Sold by ..."), only while the seller is active
  (set the company to inactive to take everything off sale at once).
- **Selling is direct**: there is no partner quote. The order goes to payment, the seller is notified when it is paid,
  sees the delivery address, and ships with any carrier (see `SHIPPING.md`). Delivery completes the sale.

## Not built yet

- Seller payouts: the platform commission is recorded per order, but the seller's share is not paid out or reported yet.
- "Needs more information" back-and-forth: a reviewer can only approve or reject today; the business then applies again.
- Seller stock levels, returns and refunds.
