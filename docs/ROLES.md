# Who is who on the platform

## The structure
**ArmorHub is the parent company.** It runs one **market per country**; a market is opened (and named) by the platform admin, can run several **industries**, and has its **own payment, shipping and fee settings**. Everyone else works *inside* a market.

| Term | What it is | Example (demo data) |
|---|---|---|
| **Platform** | The whole system and its staff, organised in **departments**. | ArmorHub |
| **Market** (tenant) | One country: currency, VAT, phone rules, its industries, its payment/shipping/fee settings and its public store. Opened by the platform admin. | ArmorHub Egypt (EG, 4 industries) |
| **Market manager** (`company-owner`) | Runs one market: its settings, storefront, catalogue, warehouse and team. Cannot touch other markets or platform-wide settings. | `owner.auto@demo.eg` |
| **Seller** (`seller`) | A verified business with an **account inside one market** (Amazon-style: no store of its own). Registering in another country's market is a separate, independent account under that market's terms. | Khan El-Khalili Crafts |
| **Centre / partner** | A workshop or service business (installs, repairs, cleans) with its own team (manager + technicians). | Zamalek Detailing Studio |
| **Customer** | A buyer of a market (or a guest at checkout). | Nour El-Din Fawzy |
| **Warehouse team** (`warehouse-staff`) | Receives sellers' cartons, counts stock, packs and ships orders of products ArmorHub stores. One market only. | — |

## Products, categories, SKU, cartons, fulfilment
- Every product is filed under the **platform category list** (groups → categories, English + Arabic names, managed by the admin; nobody types a category).
- Every product has a **SKU** (the seller's own code, or `AH-<country>-<number>` generated) and carton data: **units per carton, carton length × width × height (cm), weight (kg), cartons in stock**.
- **Fulfilment** per product: *I store and ship it myself* (default) or *ArmorHub stores and ships it*. The second needs complete carton data and an open warehouse in the market. The seller announces a delivery of cartons; the warehouse counts them on arrival (units = cartons × units per carton), orders are packed and shipped from there, and every stock change is a recorded movement. The shop shows "Fulfilled by ArmorHub" and "Out of stock".

## Who may invite whom (delegation)
Nobody can hand out more than their own area. Invitations are e-mailed links valid 7 days; the person chooses a name and password.

| Who | May invite | Into |
|---|---|---|
| Platform admin | Any role; names department heads; creates/renames departments and roles | Anywhere |
| Market manager | Warehouse staff, co-managers | Their own market company only |
| Department head | The roles of **their own department** (which they create from the department's permission area) | Their own department only |
| Seller | Teammates (role `seller`) | Their own seller company only |
| Centre manager | Managers and technicians | Their own centre only |
| Technician, customer, warehouse staff, everyone else | Nobody | — |

## Platform departments (each has one head who manages only that department)
Customer service · Procurement · Market managers · Seller management · Technology · Service-centre management · Warehouse · Sales · Disputes · Document verification. A head creates **roles** for their department from the permissions of the department's area, invites members into them, changes their role or switches them off — never beyond their department, and never their own access.

## The roles (summary)
| Role | What they can do | Cannot do |
|---|---|---|
| **Platform admin** | Everything in every market: markets, categories, departments, roles, settings, disputes, payouts, security. | — |
| **Market manager** (`company-owner`) | Their market's payments/shipping/fees, storefront, catalogue, warehouse, team, partner applications. | Other markets; platform-wide settings; sellers' products. |
| **Partner manager** | Quotes, installation offers, jobs, wallet, technicians of their centre. | Other centres. |
| **Technician** | Only assigned jobs and proof photos. | Prices, wallet, team. |
| **Seller** | Own products (category, SKU, cartons, fulfilment), orders and shipping, earnings, warehouse deliveries, own team. | Other sellers; the shared catalogue. |
| **Warehouse staff** | Warehouses, incoming deliveries, stock, orders to pack and ship — in their market. | Everything else. |
| **Customer** | Buys, requests, pays, confirms, rates, disputes. No team, no roles. | Anything administrative. |
| **Department / custom role** | Only the permissions it was built from. | Anything outside its area. |

## "The market's shared catalogue" vs "a seller's products"
Shared catalogue = products/services with no seller, changed by the market manager and admin. Seller products can be changed only by that seller and the admin.

## Money
The customer pays on the platform. Goods: the seller's share is held, then released after delivery. Installation: the centre's share is held, then released after proof and the 48-hour window. Fees are set per market.
