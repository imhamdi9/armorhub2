# Who is who on the platform

## The building blocks
| Term | What it is | Example (demo data) |
|---|---|---|
| **Platform** | The whole system. | ArmorHub |
| **Market** (tenant) | One country + one industry, with its own currency, VAT, phone rules and catalogs. | "Pharaoh Auto", Egypt, cars |
| **Store** (storefront) | The public shop of a market: its page, colours, catalogue and checkout. Run by the **store owner**. | Pharaoh Auto Care (`/s/pharaoh-auto-care`) |
| **Centre / partner** | A workshop or service business that does the work (installs, repairs, cleans). It answers quote requests and installation requests. | Zamalek Detailing Studio |
| **Seller** (merchant) | A verified business that sells **its own goods** inside a store and ships them. | Khan El-Khalili Crafts |
| **Customer** | A person who buys goods or asks for services. Belongs to the store they registered at (or buy as a guest). | Nour El-Din Fawzy |

## The roles
| Role | Who it is | What they can do | Cannot do | Demo login |
|---|---|---|---|---|
| **Platform admin** (مدير المنصة) | The operator of ArmorHub itself. | Everything, in every market: settings, payment and shipping providers, content and theme, roles, security, disputes, withdrawals and payouts, approve partners/sellers, create markets and stores. | — | `admin@demo.eg` |
| **Store owner** (`company-owner`, مالك المتجر) | The business that runs one store/market. | Edits the store's page and branding; manages the **shared catalogue** (products and services with no seller); manages the store's staff; decides partner applications for the store; sees the store's orders and commissions. | Other stores; platform settings; sellers' products. | `owner.auto@demo.eg` |
| **Partner manager** (`partner-manager`, مدير مركز الصيانة) | The manager of one centre. | Answers quote requests, sends installation offers, assigns and watches jobs, sees the centre's **wallet** and withdraws, creates **technicians** for their centre. | Other centres; the store's catalogue. | `manager.auto1@demo.eg` |
| **Technician** (`technician`, الفني) | An employee of a centre. | Only the jobs assigned to their centre: schedule, start, finish, upload proof photos. | Prices, quotes, wallet, team. | `tech.auto1@demo.eg` |
| **Seller** (`seller`, التاجر) | An independent business selling goods. | Its **own products** (create, price, photos, installation setting), its orders and shipping, its earnings. | The store's shared catalogue; other sellers; customers' data beyond what shipping needs. | `seller.khan@demo.eg` |
| **Customer** (`customer`, العميل) | A buyer. | Buys, asks for quotes and installation offers, pays, confirms work, rates, opens disputes. | Anything administrative. | `nour.fawzy@demo.eg` |
| **Custom role** | A role an admin builds from a list of allowed permissions (never administrator powers). | Only what it was given. | — | — |

## "The store's shared catalogue" vs "a seller's products"
- **Shared catalogue** = products and services that belong to the store itself (no seller): for example *Paint Protection Film (PPF)*, *Deep Home Cleaning*. They are changed by the **store owner** and the **platform admin**.
- **Seller products** = goods that belong to one seller (for example *Khan El-Khalili Brass Lantern*). Only **that seller** and the **platform admin** can change them. A store owner cannot.
- This is why the bulk "installation" action changes only what the person may change and reports the rest as skipped.

## Who sees what in the money
- The customer pays on the platform. For goods: the **seller's** share is held, then released after delivery. For installation: the **centre's** share is held, then released after proof and the 48-hour window.
- Platform fee: set by an admin per market (installation: 5% by default).

## Rules of thumb
- A person has **one role** (an admin can also hold others). The role decides the menu they see.
- *Store owner* ≠ *seller*: the owner runs the shop window; a seller is a tenant merchant inside it.
- *Partner manager* ≠ *technician*: the manager runs the centre; the technician only does assigned work.
- *Partner (centre)* is a **business place**; *partner manager* and *technician* are **people** working for it.
