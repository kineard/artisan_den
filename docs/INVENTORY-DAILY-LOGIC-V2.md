# Inventory Daily Logic (V2) – Input Sales + Purchases, Compute On Hand

## Current Logic (what we have now)

- You enter **on hand** per product per date (daily snapshots).
- **Sales** are *extrapolated*: `sales = prev_on_hand + received − curr_on_hand`.
- **Received** comes only from orders marked "Received" (no manual purchase column).
- So: we *infer* sales from on-hand changes; we don’t type in sales or purchases per day.

## New Logic (what you want)

1. **Starting inventory**  
   One value per product (e.g. "on hand at start of week"). This is the baseline for the first day. **Initial quantity is set when you add a product to inventory** (+ Add Product → Add Product to Inventory modal: “Initial quantity (on hand)”). That value is stored in `inventory.on_hand` and used as the baseline for daily On Hand.

2. **Sales per day**  
   You **enter** how many were sold each day. If 0, leave 0. No extrapolation.

3. **Purchases per day**  
   A **column for purchases** (received stock) per product per date. You enter what came in that day (from orders or other). Today there is no such column; we only have "Received" from the order flow. We want to track the purchases a recomendations of how much to purchase and when it was recieved. This will be how we increase the inventory levels.

4. **On hand = computed**  
   For each date:  
   `on_hand[date] = on_hand[date−1] + purchases[date] − sales[date]`.  
   First date: `on_hand[first] = starting_inventory + purchases[first] − sales[first]`.

5. **Update button**  
   At top or bottom of each date column (or one "Update" for the whole grid):  
   When clicked, it **recalculates** on hand for that date (or all dates) using the formula above and **saves** the new on-hand values. So you type Sales and Purchases, then click Update to refresh On Hand.

6. **No extrapolation**  
   We **stop** computing sales from on-hand deltas. Sales and purchases are **input**; on hand is **output** of the formula.

---

## Clarifications

- **Starting inventory**  
  - Option A: One "Starting" column (e.g. first day of the range) that you fill once; that day’s on hand is then `starting + purchases − sales`.  
  - Option B: "Starting" is the same as "On Hand" for the day *before* the first date (you’d set that somewhere).  
  - Recommendation: Option A – one **Starting** column for the first date, then each date has Sales | Purchases | On Hand. agreed.

- **Purchases**  
  - Today "received" only comes from the **Orders** flow (mark order Received).  
  - You want a **visible column** where you can type "how many we received today" per product.  
  - So we need either:  
    - A new table/store for **manual daily purchases** (e.g. `product_daily_purchases`: store, product, date, quantity_received), or  
    - Reuse/combine with orders: when you mark an order "Received" we could auto-fill that date’s "Purchases" for that product, and still allow manual override or extra entries.  
  - Recommendation: Add **product_daily_purchases** (or `product_daily_received`) so you can type purchases per day; optionally we can also auto-fill from "Received" orders so you don’t double-enter.
No i don't thin so. We have a column Order we input the order qty into the field of the product. Once recieved it is marked received and automatically adds the qty to the inventory.  
- **Update button**  
  - Per date: one "Update" per date column that recomputes on hand for that date only.  
  - Or one "Update all" that recomputes on hand for all dates in the grid (walking day by day: start → then each day = prev on hand + purchases − sales).  
  - Recommendation: **One "Update" per date column** so you can fix a single day without touching others; plus an optional "Update all" at the top.
We still want to track time from date of payment to date of reciept. This will help us track the inventory levels and when to order more.
---

## Proposed table layout (per product, per date)

For each **date** we show:

| Sales (input) | Purchases (input) | On Hand (computed, saved on Update) |
|---------------|-------------------|-------------------------------------|

- **Starting** (only for the first date, or as a separate column): one input per product = on hand at start of first day.
- **Update** button at bottom (or top) of each date column: recalc and save on hand for that date.

So the grid could look like:

- Row = product.  
- Columns = for each date: **Sales** | **Purchases** | **On Hand** (read-only or editable only via Update).  
- Optional: one **Starting** column at the left (for the first day’s starting on hand).  
- **Update** under each date (or one "Update all").

---

## Schema changes (high level)

1. **product_daily_sales**  
   Keep as-is. Use it for **user-entered** sales (quantity_sold = what you type). Stop overwriting it with extrapolated values.

2. **product_daily_purchases** (new)  
   e.g. `store_id, product_id, purchase_date, quantity_received`  
   So we can store **user-entered** purchases per product per date.

3. **inventory_snapshots**  
   Keep. **On hand** per product/date is now the *result* of Update:  
   `on_hand[d] = on_hand[d-1] + purchases[d] − sales[d]` (with starting inventory for day 0).

4. **Starting inventory**  
   Either:  
   - First date’s snapshot is the "starting" value you set, then we compute forward; or  
   - Add a "starting_on_hand" per product (e.g. in inventory or a small table) for "as of first day".  
   Simplest: **Starting** is just the on-hand value you enter (or we pre-fill from snapshot) for the **day before the first date**; then we compute from there. Or we add one "Starting" column and treat it as on_hand for the day before the first date.

---

## Summary

- **You enter:** Starting inventory (once per product for the range), Sales per day, Purchases per day.  
- **System does:** When you click **Update** (per date or "Update all"), it computes On Hand from `prev_on_hand + purchases − sales` and saves it. No extrapolation of sales.  
- **We add:** A **Purchases** column and storage (e.g. product_daily_purchases), and **Update** button(s).  
- **We change:** Sales become direct input (and we stop overwriting them with extrapolation). On hand becomes the computed result of the formula.

If this matches what you want, next step is to implement: schema (e.g. product_daily_purchases), UI (Starting + Sales + Purchases + On Hand + Update), and backend (recalc and save on hand when Update is clicked).

---

## Review of your changes & questions

### What we understood from your edits

1. **Starting inventory:** When adding a new product into the system, Starting should default to the **current on hand** value (from inventory). Agreed and we’ll do that.

2. **Purchases = from Orders, not a separate manual table:** You don’t want a separate “type purchases per day” table. You already have an **Order** column where you enter order qty; when you mark it **Received**, the system should **automatically** add that qty to inventory (and treat it as “purchases” for that received date). So “Purchases” in the daily grid would be **driven by Received orders** (e.g. sum of quantities received that day for that product), not a second manual entry.
I do not see a way to add Orders. I see columns for Qty Ordered and Qty Received. but no way to add an order.
3. **Lead time:** You want to **track time from date of payment (order date) to date of receipt**. That helps with “when to order more” and reorder timing. We’ll keep using `order_date` and `received_date` (and optionally `expected_delivery_date`) and can surface lead time (e.g. per vendor or per product).
agree with this portion
### Questions for you

1. **Purchases column in the daily grid**  
   Should the “Purchases” column for each date be:
   - **A) Read-only:** Only show what was received that day from orders (mark Received → auto-add to inventory and show in Purchases for that date), no manual typing, or  
   - **B) Editable:** Show received-from-orders by default but **allow manual override or extra** (e.g. you received stock outside the Order flow – cash purchase, transfer – and want to type it in)?  
   Which do you prefer?agreed with this portion

2. **“Recommendations of how much to purchase”**  
   You wrote: “track the purchases [and] recommendations of how much to purchase and when it was received.”  
   - “How much to purchase” = we already have **Suggested Order** in the Inventory & Reorder list.  
   - Do you want that same “suggested order” visible or duplicated **inside the daily grid** (e.g. a column or tooltip per product), or is it enough to keep it only in the Inventory & Reorder list above? I was just reiterating the same information.

3. **Update button and “Purchases” from Received**  
   When you mark an order **Received** today, we add that qty to inventory and could add it to “Purchases” for today. When you then click **Update** for that date, should we:
   - **Only** recompute: `on_hand = prev_on_hand + purchases_from_orders_that_day − sales` (no separate purchase input), or  
   - Allow **both** “received from orders” and any **manual purchase** entry for that day (if you chose B above)?  
   Your answer to question 1 will drive this.
on hand + Received = total on hand.and allow manual transfers and manual entries.
### Recommendations

1. **Default Starting from current on hand**  
   When we add a product to the daily grid (or when we first show the Starting column), pre-fill **Starting** from `inventory.on_hand` for that product/store so you don’t have to retype it.

2. **Lead time in one place**  
   Store **order_date** and **received_date** on orders (already there). Optionally compute **actual lead time** (received_date − order_date) and show it in the Inventory & Reorder list (e.g. “Lead time: 4 days (avg from last 3 orders)” or next to the Received button) so “when to order more” is visible without opening the daily grid.

3. **No separate product_daily_purchases table if Purchases = Received only**  
   If you choose **A** (read-only Purchases from orders), we don’t need a new `product_daily_purchases` table. We derive “purchases per date” from existing **orders** where `status = 'RECEIVED'` and `received_date = that date`. If you choose **B** (editable/override), we’d add a small table or fields for manual purchase entries per product/date so you can add stock that didn’t go through an Order.

4. **Schema**  
   - Keep **product_daily_sales** for user-entered sales.  
   - Keep **inventory_snapshots** for computed on-hand (updated when you click Update).  
   - **Purchases** = from orders (received_date, quantity) when status is RECEIVED; optionally add manual purchase storage only if you want B above.

Once you answer the questions above (especially 1 and 2), we can lock the behavior and implement it.

---

## Your answers (summary)

| Question | Your answer | Locked? |
|----------|-------------|--------|
| **1. Purchases column** (read-only vs editable) | You wrote “allow manual transfers and manual entries” for Q3 → we’re treating this as **B) Editable**: show received-from-orders and **allow manual entries** (transfers, cash purchases, etc.). | ✓ |
| **2. Suggested order in daily grid?** | “I was just reiterating the same information.” → Keep suggested order **only** in the Inventory & Reorder list; no duplicate in the daily grid. | ✓ |
| **3. Update / Purchases formula** | “on hand + Received = total on hand. and allow manual transfers and manual entries.” → **On hand** = prev on hand + Received (from orders) + manual purchases/transfers − sales. Allow manual entries. | ✓ |
| **Lead time** | “agree with this portion” → Track order date → received date; surface lead time (e.g. in reorder list). | ✓ |

**New requirement you raised:** “I do not see a way to add Orders. I see columns for Qty Ordered and Qty Received but no way to add an order.”  
→ We’ll add or make obvious a way to **create an order** (e.g. “Order” button per product that records qty ordered, order date, expected delivery; then “Received” marks it received and adds to inventory and to Purchases for that date).

**If anything above is wrong**, say which line and we’ll fix it. Otherwise we can proceed to implement with: Starting + Sales + Purchases (from Received + manual), On Hand computed on Update, and a visible “add order” flow.
