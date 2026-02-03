# Daily On-Hand System Improvements - Discussion Document

## Current Concerns & Requirements

### 1. Product Selection (Top 10 → Top 20)

**Current State:**
- Shows top 10 products
- Fixed list

**Requirements:**
- Not always the same top 10 products
- Don't want entire catalog (too many products)
- **Proposed Solution:** Top 20 products with scrollable input field
- Products should be dynamically selected (not hardcoded)

**Questions:**
- How should we determine "top 20"? 
  - By sales volume? Yes
  - By frequency of reorders?
  - By user selection/preference?
  - By current stock status (LOW/OUT items first)?
- Should users be able to manually add/remove products from this list? yes
- Should this be configurable per store? yes

---

### 2. Graph & First Column Display Options

**Requirements:**
- Show options for different time periods/calculations:
  - Today/Current day's input
  - Top (need clarification - what does "top" mean here?)
  - 7 day average
  - 30 day average
  - 1 year average

**Questions:**
- Where should these options appear? 
  - Dropdown selector above the grid?
  - Toggle buttons? yes
  - Separate tabs?
- For the graph: Should this be a selector that changes what data is displayed?yes
- For the first column: Should this change what's shown in the "Product" column, or add additional columns?Add additional columns Just for 7 day and 30 day averages
- What does "Top" refer to? Top selling products? Top priority items? Top selling products

---

### 3. Order Tracking & Received Button

**Current Logic Issues:**
- No way to track "ordered today" items
- System might suggest reordering items that are already ordered but not received
- No clear indication when something is in transit

**Requirements:**
- **Input field for "Ordered Today"**: Track what was ordered today
- **Block reordering**: If item is already ordered (not received), don't suggest reordering again
- **Received button**: Clear the "ordered" status when stock arrives
- **Visual blocking**: While waiting for receipt, the order block should be visually blocked/disabled

**Proposed Solution:**
- Add an "Ordered Date" field to inventory items (or use existing orders table)
- When an order is placed, mark it as "ORDERED" status
- Show "Awaiting Receipt" indicator in the inventory list
- Add "Mark as Received" button that:
  - Updates order status to "RECEIVED"
  - Updates inventory on_hand
  - Clears the "ordered" flag
- In suggested order calculations, exclude items with pending orders

**Questions:**
- Should we track multiple pending orders for the same product? no
- Should the "Received" button update the daily on-hand automatically? yes
- Should we show expected delivery date based on lead time? yes

---

### 4. Sales Data Priority

**Current State:**
- Daily sales extrapolation from on-hand differences

**Requirements:**
- Daily sales is less important than averages
- Focus on 7-day and 30-day averages

**Questions:**
- Should we:
  - Calculate and display 7-day average sales prominently? We have input for the day so that is being tracked so in the two columns add the 7 day and 30 day averages
  - Calculate and display 30-day average sales prominently? We have input for the day so that is being tracked so in the two columns add the 7 day and 30 day averages
  - Use these averages for reorder point calculations instead of daily? Yes lets start with the seven day average for reorder point calculations
  - Show both daily and averages, but emphasize averages? yes. but the daily is already tracked because that is our daily input. 
- Should we store historical averages in the database? no that is what the chart is for
- How should we handle products with less than 7/30 days of data? We should not input something with less than 7 days of data. 30 day put someting equivalent to NA or Not Enough Data.something that does not increase the diminsions of the table cell.

---

### 5. Data Flow & Logic Clarification

**Current Flow (as I understand it):**
1. User inputs daily on-hand quantities
2. System calculates extrapolated sales (prev_on_hand + received - curr_on_hand)
3. System suggests reorder quantities based on target_max - on_hand using the seven day average for reorder point calculations
4. User can create orders

**Proposed Improved Flow:**
1. User inputs daily on-hand quantities
2. System calculates:
   - Daily sales (for reference)
   - 7-day average sales (primary metric)
   - 30-day average sales (primary metric)
3. System suggests reorder quantities, BUT:
   - Excludes items with pending orders
   - Uses 7/30-day averages for calculations Once money is available we will order 30 day supply for top ten items. until then we will order 7 day supply. so this is a transition phase to get products to 30 day supply. That is for stuff that is not locally available.
4. User creates order → marks item as "ORDERED" do not make table cells change in dimensions so use acronyms or icons to maintain the same size cell.
5. When stock arrives → user clicks "Received" → updates on_hand and clears order status

**Questions:**
- Does this flow match your needs? Yes
- Are there other steps or considerations? No
- Should we track "expected delivery date" based on lead time? yes

---

## Proposed Database Changes

### New/Modified Fields:

1. **Orders table** (already exists, may need modifications):
   - `order_date` - when order was placed
   - `received_date` - when stock arrived (already exists)
   - `status` - ORDERED, RECEIVED, etc. (already exists)
   - `expected_delivery_date` - calculated from order_date + lead_time

2. **Inventory table** (may need additions):
   - `avg_7day_sales` - calculated 7-day average
   - `avg_30day_sales` - calculated 30-day average
   - `last_calculated_avg_date` - when averages were last updated

3. **New table: `product_daily_sales`** (optional, for historical tracking):
   - `product_id`
   - `store_id`
   - `sale_date`
   - `quantity_sold`
   - For calculating rolling averages

---

## UI/UX Proposals

### 1. Daily On-Hand Grid Improvements:
- **Scrollable product list**: Show top 20 products, scrollable if more
- **Product selector**: Dropdown or search to add/remove products from the list
- **View options**: Toggle between "Today", "7-day avg", "30-day avg", "1-year avg" for display

### 2. Inventory & Reorder List Enhancements:
- **Order status indicator**: Visual badge showing "ORDERED - Awaiting Receipt"
- **Received button**: Prominent button when order is pending
- **Blocked order suggestion**: Gray out or hide "Order" button when pending order exists
- **Average sales display**: Show 7-day and 30-day averages prominently

### 3. Graph Enhancements:
- **Data series selector**: Choose what to display (daily, 7-day avg, 30-day avg, etc.)
- **Multiple series**: Option to show multiple averages on same graph

---

## Questions for Clarification

1. **"Top" in the options list** - What does this refer to? I i wrote options list it was in error. If I was referring to selectable options for deisplaying data that would have been a drop down menu. I think we have enough room to just make additional columns for the 7 day and 30 day averages.
2. **Product selection method** - How should we determine which 20 products to show? I think I restrained the product list to much. We should be able to input as many products as we want. We will only input the top 10 in our POS on a daily basis maybe top 15 and it will naturally trend int to the top 10 list and sort order will be by top ten. 
3. **Order blocking logic** - Should we block ALL reorders while one is pending, or allow multiple orders if needed? Not at this time one order at a time.
4. **Average calculation** - Should averages include days with 0 sales, or only days with actual sales? days with 0 sales should be included.
5. **Historical data** - Do you want to keep historical daily sales data, or just calculate on-the-fly from snapshots? Yes keep historical daily sales data.
6. **Priority** - What's the most important feature to implement first? what ever is most logically next.

---

---

## Review & Logical Issues Found

### Issues to Clarify:

1. **Product Limit Contradiction**: 
   - Section 1 says "Top 20 products" but Question 2 says "We should be able to input as many products as we want"
   - **Recommendation**: Remove "Top 20" limit - make it unlimited with scrollable list, sorted by sales volume (top sellers first)

2. **Reorder Point vs Target Max Logic**:
   - You want 7-day average for reorder point calculations
   - But also mention "order 30 day supply for top ten items" and "transition to 30 day supply"
   - **Clarification needed**: 
     - Should `reorder_point` use 7-day average? ✓ (confirmed)
     - Should `target_max` use 30-day average for top 10, 7-day for others? Or always 7-day for now?
     - Should there be a flag/priority field to mark "top 10" items that get 30-day supply?

3. **Historical Data Storage**:
   - Question 4 says "no that is what the chart is for" (don't store averages)
   - Question 5 says "Yes keep historical daily sales data"
   - **Clarification**: I believe you mean:
     - ✓ Store daily sales in `product_daily_sales` table (for calculations)
     - ✗ Don't store calculated averages in inventory table (calculate on-the-fly)
   - **Recommendation**: Create `product_daily_sales` table to store historical daily sales

4. **"Received" Button Location**:
   - **Question**: Should the "Received" button appear:
     - In the Inventory & Reorder List table (as a column)?
     - In a separate "Pending Orders" section?
     - Both places?
   - **Recommendation**: Show in Inventory list when order is pending, with expected delivery date

5. **Expected Delivery Date Display**:
   - **Question**: Where should expected delivery date be shown?
     - In the Inventory list as a column?
     - As a tooltip on the "Awaiting Receipt" badge?
     - In the order details modal?
   - **Recommendation**: Show as small text next to "Awaiting Receipt" badge (e.g., "Expected: Jan 30")

6. **Sort Order Clarification**:
   - "sort order will be by top ten" - does this mean:
     - Sort by 7-day average sales (descending)?
     - Or manually mark "top 10" and sort those first?
   - **Recommendation**: Sort by 7-day average sales descending (top sellers first)

### Suggested Improvements:

1. **Database Schema**:
   - Add `expected_delivery_date` to orders table (calculated: order_date + lead_time_days)
   - Create `product_daily_sales` table for historical tracking
   - Add index on (store_id, product_id, sale_date) for fast lookups
   - Consider adding `is_top_10` flag to inventory table for 30-day supply logic

2. **UI Improvements**:
   - Add "Add Product" button to daily on-hand grid header
   - Show product count in grid header (e.g., "15 products")
   - Make 7-day and 30-day average columns sortable
   - Add tooltip on "N/A" for 30-day when <30 days of data

3. **Logic Improvements**:
   - When calculating 7-day average: require minimum 7 days of data
   - When calculating 30-day average: show "N/A" if <30 days, but still calculate with available data
   - When order is placed: automatically set expected_delivery_date = order_date + lead_time_days
   - When "Received" is clicked: update on_hand, set received_date, update status to RECEIVED, clear order from pending list

4. **Performance Considerations**:
   - Calculate averages on page load (cache in memory for session)
   - Consider background job to pre-calculate averages daily
   - Index product_daily_sales table properly for fast queries

---

## Implementation Priority (Logical Order)

1. **Phase 1: Database & Data Storage**
   - Create `product_daily_sales` table
   - Add `expected_delivery_date` to orders table
   - Modify order creation to calculate expected delivery date
   - Update daily sales calculation to store in new table

2. **Phase 2: Average Calculations**
   - Calculate 7-day average sales (from product_daily_sales)
   - Calculate 30-day average sales (from product_daily_sales)
   - Display in new columns in Inventory & Reorder List
   - Handle "N/A" for <30 days data

3. **Phase 3: Reorder Logic Updates**
   - Update reorder point calculation to use 7-day average
   - Update target_max calculation (clarify 7-day vs 30-day logic)
   - Exclude items with pending orders from suggested orders

4. **Phase 4: Order Tracking UI**
   - Add "Awaiting Receipt" badge/indicator
   - Add "Received" button in inventory list
   - Block "Order" button when pending order exists
   - Show expected delivery date

5. **Phase 5: Daily On-Hand Grid Improvements**
   - Remove product limit (unlimited scrollable list)
   - Add "Add Product" functionality
   - Sort by sales volume (7-day average)
   - Add product selector/search

6. **Phase 6: Graph Enhancements**
   - Add toggle buttons for data series selection
   - Add 7-day and 30-day average series to graph
   - Update chart to show selected series

---

## Next Steps

Please review the issues and suggestions above and:
1. Clarify the reorder point vs target_max logic (7-day vs 30-day)
2. Confirm the "Received" button location preference
3. Confirm expected delivery date display location
4. Confirm sort order method (by 7-day average sales?)
5. Approve the implementation priority order

Once clarified, I'll implement the changes systematically following the priority order above.
