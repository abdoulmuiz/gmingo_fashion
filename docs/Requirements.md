# Fashion ERP Requirements

Gmingo SaaS: subscription ERP for fashion companies

## 1. Purpose and Scope

This document lists the requirements for a subscription ERP for fashion companies, part of the Gmingo SaaS system. Companies subscribe and choose the modules they need, from made-to-order tailoring to factory production and ready-to-wear sales.

Three kinds of company are expected to subscribe first, and each uses a different mix of modules.

| Company type | Modules they mainly use |
| --- | --- |
| Tailors and small made-to-order shops | Customers and orders, production, measurements, payments, communication |
| Garment factories and manufacturers | Production, inventory and purchasing, people and worker pay, quality |
| Fashion brands and boutiques selling ready-to-wear | Products and sales, inventory, customers, offers and broadcasts |

## 2. System Requirements

Sections 2.1 to 2.28 cover customers, orders, production, stock, communication and staff. Sections 2.29 to 2.37 add the wider ERP modules. A company switches on only the modules it needs.

| Module | Sections |
| --- | --- |
| Customers and orders | 2.1, 2.2, 2.16, 2.17 |
| Production | 2.3, 2.10, 2.23, 2.25, 2.31 |
| Payments and finance | 2.4, 2.18, 2.20, 2.22, 2.34, 2.36 |
| Measurements and design | 2.6, 2.12 |
| Communication | 2.7, 2.8, 2.24, 2.26, 2.27 |
| Inventory and purchasing | 2.9, 2.30, 2.32 |
| Products and sales | 2.29, 2.33 |
| Staff management | 2.11, 2.19, 2.35 |
| Setup, language, reporting and AI | 2.5, 2.13, 2.14, 2.15, 2.21, 2.28, 2.37 |

### 2.1 Customers

The customer record itself lives in the shared core. The Fashion module adds the views and actions below.

- FR-1.1: The system shall list and search customers.
- FR-1.2: The system shall provide a new customer form with name, phone, gender and type (VIP or Regular).
- FR-1.3: The customer profile shall show number of orders, total spent, amount owed, customer since date and order history.
- FR-1.4: The profile shall have Call, WhatsApp and New Order buttons.

### 2.2 Orders

- FR-2.1: Each order shall have an order number unique per business (for example TK1048), a customer, a due date and a paid or unpaid tag.
- FR-2.2: An order shall hold several items, each with garment, quantity, price per piece, fabric and notes.
- FR-2.3: An order can be flagged as urgent.
- FR-2.4: The order list shall have filter tabs: All, Active, Overdue, Due today, Urgent, Ready, Delivered and Unpaid.
- FR-2.5: The order list shall support search.

### 2.3 Production Stages

- FR-3.1: The default stages are New, Cutting, Sewing, Embroidery, Finishing, Fitting, Ready and Delivered.
- FR-3.2: A "Move to next stage" button shall advance an order, and each move shall record who made it and when.
- FR-3.3: Each business can rename, add or skip stages.
- FR-3.4: A board view shall show orders by stage, with the assigned staff member on each order card.

### 2.4 Payments (uses shared Billing)

- FR-4.1: Every order shall show total, amount paid and balance.
- FR-4.2: Users can record a payment with its method (transfer or cash), and a payment history shall be kept.
- FR-4.3: The system shall show the outstanding balance across all orders.

### 2.5 Dashboard

- FR-5.1: The dashboard shall show cards for active orders, due this week, overdue, outstanding money, ready for pickup and low stock.
- FR-5.2: The dashboard shall list overdue and urgent orders.

### 2.6 Measurements

- FR-6.1: Supported garment types are Kaftan, Senator, Agbada, Shirt, Trouser and Gown.
- FR-6.2: Each garment type has its own measurement fields. For example, a Kaftan has neck, shoulder, chest, stomach, sleeve length, round sleeve, cuff and top length.
- FR-6.3: Every save shall create a new dated version and never overwrite an earlier one. Both "Take new measurement" and "Update measurement" create a version.
- FR-6.4: The unit (inches or cm) shall be stored with the values.
- FR-6.5: Measurements shall stay attached to the customer.
- FR-6.6: Measurement fields shall be editable per business and not hard-coded.

### 2.7 Customer Tracking and Messaging

- FR-7.1: The system shall provide a "Copy tracking link" action that opens a public page showing shop name and phone, order number, status, expected date, progress timeline and balance due.
- FR-7.2: The tracking page shall show payment details (bank or Opay).
- FR-7.3: The tracking page shall have a "Chat with shop" button that opens WhatsApp.
- FR-7.4: A "Running late" notice shall send an apology and a new expected date.
- FR-7.5: A "Send ready message" action shall notify the customer that the order is ready.
- FR-7.6: The system shall send automatic messages for order confirmation, deposit reminder, fitting booking, and ready for pickup with balance.
- FR-7.7: A Messages section shall show what has been sent.

### 2.8 Fittings and Calendar

- FR-8.1: Users can schedule a first fitting from the order page.
- FR-8.2: A calendar shall show fittings and due dates in different colours.

### 2.9 Inventory

- FR-9.1: The system shall track fabric and supplies such as thread by colour, zips, buttons, lining, interfacing, packaging and shop fabric.
- FR-9.2: Each item shall have a quantity with a unit (cones, yards, pieces) and a minimum level.
- FR-9.3: Plus and minus buttons shall change the quantity.
- FR-9.4: Every change shall be recorded as an append-only stock movement showing who changed what.
- FR-9.5: Low-stock items shall raise an alert on the dashboard and appear in a "Low stock items" section.

### 2.10 Capacity and Delivery Dates

- FR-10.1: A shop capacity meter shall show units in production against a maximum (for example 31 of 40).
- FR-10.2: The system shall suggest the earliest delivery date for a new order by garment type, based on capacity and how long each garment takes.

### 2.11 Staff and Access

- FR-11.1: The system shall support staff roles.
- FR-11.2: A tailor shall see only their own jobs.

### 2.12 Design Library

- FR-12.1: The system shall store reference photos of styles.
- FR-12.2: Photos can be attached to orders.

### 2.13 Settings

- FR-13.1: A business can configure shop name, phone, payment details, stage names, garment types and capacity.

### 2.14 Approvals

- FR-14.1: The system shall require approval before work is handed over while a balance is still owing (from the architecture plan).

### 2.15 Reports

- FR-15.1: The system may provide reports on revenue, outstanding balances and orders by stage. This is an idea only, because the desktop revenue dashboard in the reference video could not be read.

* FR-15.2: Reports shall also show best-selling garments, busiest months and the customers who owe the most.

### 2.16 Customer-Supplied Fabric

Many customers bring their own material. Recording it settles arguments about leftover fabric.

- FR-16.1: An order can record that the customer supplied the fabric.
- FR-16.2: The order shall record how many yards were received, how many were used and how many are left over.
- FR-16.3: The leftover amount shall be visible on the order.

### 2.17 Group and Event Orders

Aso ebi, uniforms and wedding parties are common: one fabric, many people, and one person or several paying.

- FR-17.1: The system shall support a group order that holds many individual orders.
- FR-17.2: A group order shall show a group total.
- FR-17.3: A group order shall show who has paid, whether one person pays for all or each person pays separately.

### 2.18 Deposit Rules

This protects tailors from doing work nobody has paid for. The architecture plan already has this idea.

- FR-18.1: Each shop can set a deposit percentage.
- FR-18.2: The system shall block the Cutting stage until the deposit is recorded.

### 2.19 Worker Pay

Many shops pay tailors per garment. Stage moves already record who did each stage (FR-3.2), so most of the data exists.

- FR-19.1: The system shall record which worker did which stage.
- FR-19.2: A rate per garment type shall be set for worker pay.
- FR-19.3: The system shall show a weekly total owed to each worker.

### 2.20 Expenses and Profit

This gives profit per order and per month, which owners rarely know.

- FR-20.1: Users can record expenses such as fabric, thread, rent, transport and wages.
- FR-20.2: The system shall show profit per order and per month.

### 2.21 Language Support

For a shop in Kano this is a selling point.

- FR-21.1: The system shall support five languages: Hausa, English, Yoruba, Igbo and Pidgin (Broken English).
- FR-21.2: Users can change the language from within the app, and screens shall show in the chosen language.
- FR-21.3: Customer messages shall work in all five languages, and each message template shall be stored in all five from the start.

### 2.22 Receipts and Invoices

- FR-22.1: The system shall produce receipts and invoices as a PDF or image, ready to send on WhatsApp.

### 2.23 Alterations and Redo

- FR-23.1: When a fitting sends an order back to sewing, the system shall record a reason (for example, "too tight at the chest") and a count.
- FR-23.2: The shop shall be able to see how often work is redone.

### 2.24 Collection Reminders

Finished garments take up space in the shop.

- FR-24.1: The system shall send reminders for finished work that nobody has picked up.

### 2.25 Job Tags with QR Code

- FR-25.1: The system shall produce a job tag with a QR code that can be printed and attached to the garment.
- FR-25.2: Scanning the tag shall open the order.
- FR-25.3: A worker can move the order to the next stage from their phone after scanning.

### 2.26 Customer Feedback

- FR-26.1: After delivery, the system shall ask the customer for feedback, such as a simple rating or "how did we do?".

### 2.27 Offers and Broadcasts

- FR-27.1: Shops can send offers and broadcasts to past customers, such as an Eid or Sallah message.
- FR-27.2: The system shall send these only to customers who agreed to receive messages, as the architecture plan says.

### 2.28 Multiple Branches

- FR-28.1: The system shall support a business with more than one branch.

### 2.29 Product and Style Catalogue

- FR-29.1: The system shall store products and styles with name, photos, sizes and colours.
- FR-29.2: Each style shall have a bill of materials: the fabric and supplies needed for one piece.
- FR-29.3: The system shall show the estimated material cost of a style from its bill of materials.

### 2.30 Purchasing and Suppliers

- FR-30.1: The system shall keep supplier records.
- FR-30.2: Users can raise purchase orders for fabric and supplies.
- FR-30.3: Receiving goods against a purchase order shall add the items to stock as stock movements.
- FR-30.4: The system shall show the amount owed to each supplier.

### 2.31 Production Planning and Quality

- FR-31.1: Users can create work orders for a batch of one style, with quantities by size.
- FR-31.2: Work orders shall be scheduled by stage and worker, against shop capacity (section 2.10).
- FR-31.3: The system shall record work sent to outside workers: what was sent, to whom and when it is due back.
- FR-31.4: A quality check shall be recorded before an order is marked Ready, with a pass or fail and a reason. A fail can send the order back for redo (section 2.23).

### 2.32 Warehouses and Finished Goods

- FR-32.1: The system shall track raw materials and finished goods separately.
- FR-32.2: A company can have more than one store or warehouse, with transfers between them.
- FR-32.3: Every stock change shall be an append-only stock movement (DR-2).

### 2.33 Sales: Retail, Wholesale and Online

- FR-33.1: The system shall record ready-to-wear sales through retail, wholesale and online orders.
- FR-33.2: Each sale shall produce an invoice or receipt (section 2.22).
- FR-33.3: A sale of finished goods shall reduce stock.

### 2.34 Finance

- FR-34.1: The system shall show money owed by customers.
- FR-34.2: The system shall show money owed to suppliers and workers.
- FR-34.3: Expenses and profit are covered in section 2.20.

### 2.35 Staff Management

The owner uses this module to manage all staff from one place. It works together with roles and access (section 2.11) and worker pay (section 2.19).

- FR-35.1: The owner can add, edit and deactivate staff, with name, phone, role and start date.
- FR-35.2: The owner can set a role for each staff member, and the role decides what that person can see and do.
- FR-35.3: The owner can assign orders and stages to staff, and each staff member sees their own assigned jobs.
- FR-35.4: The system shall record daily attendance for each staff member.
- FR-35.5: The owner can see each staff member's work: jobs finished per stage by week, and how many of their jobs were sent back for redo (section 2.23).
- FR-35.6: Staff can be paid per garment (section 2.19) or by a fixed salary, and the system shall show what is owed to each person and keep a history of pay given.
- FR-35.7: Staff can log in from their phone to see their jobs and move an order to the next stage, including by scanning a job tag (section 2.25).
- FR-35.8: The owner can remove a staff member's access straight away.
- FR-35.9: Staff actions shall appear in the activity log (PR-5).

### 2.36 Payment Module (Paystack or Manual)

Each business chooses how it collects and records payments. This builds on payments and balances (section 2.4).

- FR-36.1: Each business shall choose a payment mode in settings: Paystack (automatic) or Manual. The choice can be changed later.
- FR-36.2: In Manual mode, users record each payment themselves with the amount, method (transfer or cash) and date, as in section 2.4.
- FR-36.3: In Paystack mode, the business connects its own Paystack account, so customer payments go to the business.
- FR-36.4: In Paystack mode, the system shall create a payment link for an order's deposit or balance, which can be sent on WhatsApp and shown on the customer tracking page (section 2.7).
- FR-36.5: When Paystack confirms a payment, the system shall record it automatically, update the order's paid amount and balance, and produce a receipt (section 2.22).
- FR-36.6: A payment that is started but not completed shall show as pending and shall not count toward the paid amount.
- FR-36.7: In Paystack mode, users can still record cash payments manually.
- FR-36.8: A payment received through Paystack shall not be editable. An authorised user can reverse it with a reason.
- FR-36.9: The deposit rule (section 2.18) shall work in both modes: the Cutting stage unlocks once the deposit is recorded, manually or through Paystack.
- FR-36.10: Only users with permission can record or reverse payments, and both modes shall keep the same payment history.

### 2.37 AI Features

AI features help owners and staff work faster, and they are meant for every type of business in the system. Each feature is optional and can be switched on or off by the business. AI suggests, and people approve.

- FR-37.1: Each business can turn each AI feature on or off in settings. Customer auto reply (FR-37.10) shall be off until the business turns it on.
- FR-37.2: Ask the system. The owner can type or speak a question about their business, such as "Who owes me the most?", "How much did I earn in December?" or "Which orders are late?", and get an answer. Answers use only that business's own data.
- FR-37.3: Message writing and translation. The system drafts customer messages in Hausa, English, Yoruba, Igbo or Pidgin, such as ready-for-pickup notices, late apologies and festival greetings. The owner approves a message before it is sent.
- FR-37.4: Delivery date suggestions. The system estimates how long each type of item or order really takes from past orders and suggests a realistic delivery date.
- FR-37.5: Reading notes and photos. A user can photograph a handwritten note, receipt or label, and the system fills in the order or expense details. The user checks and confirms before anything is saved.
- FR-37.6: Stock reorder prediction. The system warns when an item is likely to run out, based on past use.
- FR-37.7: Forecasts. The system shows expected busy periods and sales.
- FR-37.8: Customer insights. The system flags customers who are likely to return, customers who often pay late, and the best-selling items.
- FR-37.9: Style search. Users can search the product catalogue and design library by describing a style or by uploading a photo.
- FR-37.10: Customer auto reply. The system can answer customer questions on WhatsApp from order data, such as "Is my order ready?". Questions about money, complaints, or anything it cannot answer shall be passed to a staff member.
- FR-37.11: AI shall not change any record on its own. Its suggestions need a person's approval, except auto reply, which only reads order status.
- FR-37.12: AI use counts toward the plan's usage limits (PR-1) and appears in the usage meter.

## 3. Data and Design Rules

- DR-1: Measurements are versioned, never overwritten.
- DR-2: Stock changes are append-only movements, so there is always a record of who changed what.
- DR-3: Stage moves record the user and the time.
- DR-4: Stages, garment types, measurement fields and capacity are configured per business, not hard-coded.
- DR-5: The chosen language is saved, and screens and messages follow it.
- DR-6: Order numbers are unique per business.

* DR-10: Every payment records how it was received (Paystack or manual) and who recorded it.

- DR-7: Customer-supplied fabric is recorded per order as yards received, yards used and yards left over.
- DR-8: Every message template is stored in Hausa, English, Yoruba, Igbo and Pidgin.
- DR-9: Offers and broadcasts go only to customers who agreed to receive messages.

## 4. Shared Core Responsibilities

These belong to the core system, not the Fashion module. Fashion uses them.

| Feature | Owner |
| --- | --- |
| Customers | Core |
| Payments and balances | Core Billing |
| WhatsApp and SMS sending | Core Messaging |
| Public tracking page | Core, shared with Logistics |
| Stock movements | Core, shared with Trading |
| Everything else in section 2 | ERP modules |

The tracking page is shared with Logistics, so it should be built once in the core.

## 5. Platform Requirements

These are not Fashion features, but the subscription business needs them.

- PR-1: Each plan shall set limits on the number of orders, messages and staff, with a usage meter the owner can see.
- PR-2: The system shall show upgrade and renewal banners.
- PR-3: The owner shall be able to see the payment history for their subscription.
- PR-4: The owner shall be able to export their data, so a customer can take it with them.
- PR-5: The system shall keep an activity log of who changed what. Money and measurements are where owners will ask.

* PR-6: Each company shall choose which modules to subscribe to, and modules can be switched on or off for that company.

## 6. Assumptions and Constraints

- The feature list comes from what a reference video shows, not from talking to customers.
- The video shows a finished product, and the list is long, so the team should build in stages and not start later features early.
- The Agbada measurement list from the other AI was partial.
- Capacity-based delivery dates need real data from tailors on how long each garment takes.

* Customer-supplied fabric, group orders, deposit rules, worker pay, expenses and language support (sections 2.16 to 2.21) should be settled before the database tables are designed.

- Sections 2.29 to 2.35 come from general ERP practice for factories and brands, not from the reference video.

* AI features that learn from past data (delivery dates, reorder prediction, forecasts and customer insights) need a few months of real business data before they are useful.

## 7. Open Questions and Validation

- Confirm the real measurement field lists for each garment with two or three tailors in Kano.
- Ask real tailors which of the later features (messaging, fittings, inventory, capacity, design library, reports) they would pay for before building them.
- Collect production time per garment type to support the earliest delivery date feature.
- Decide whether reports are in scope, since the source for them is unclear.

* Decide what customer data may be sent to an AI service, and how AI usage is limited and priced in each plan.

- Decide who pays the Paystack fees, the business or the customer.

Confirm the needs of garment factories and fashion brands with a few real companies before building their modules.
