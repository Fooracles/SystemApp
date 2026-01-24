# Doer Dashboard Date Range Test

This test file verifies that the date range toggle functionality (7D/14D/28D/Custom) works correctly on the Doer Dashboard.

## Files

- **test_doer_dashboard_date_ranges.php** - Test file with dummy data to verify date range functionality

## How to Use

### 1. Access the Test File

Open in browser:
```
http://localhost/app-v2/test/test_doer_dashboard_date_ranges.php
```

### 2. Test Different Date Ranges

#### Test 7D Range
- Click the "7 Days" button
- **Expected:** Stats show base values (multiplier = 1)
- Example: ~15 completed, ~5 pending, ~3 delayed

#### Test 14D Range
- Click the "14 Days" button
- **Expected:** Stats approximately double (multiplier = 2)
- Example: ~30 completed, ~10 pending, ~6 delayed

#### Test 28D Range
- Click the "28 Days" button
- **Expected:** Stats are ~4x base values (multiplier = 4)
- Example: ~60 completed, ~20 pending, ~12 delayed

#### Test Custom Range
- Select a "From Date" and "To Date"
- Click "Custom Range" button
- **Expected:** Stats scale based on number of days in range
- Formula: multiplier = max(1, round(days / 7))

### 3. Verify Calculations

The test file automatically validates:

✅ **WND Calculation**
- Formula: `-((Pending + Delayed) / Total) * 100`
- Should be negative percentage

✅ **WND On-Time Calculation**
- Formula: `-(Delayed Completed / Completed) * 100`
- Should be negative percentage

✅ **Date Range Validation**
- From date should be <= To date

### 4. Integration Test with Real Dashboard

1. Open browser console (F12)
2. Navigate to: `pages/doer_dashboard.php`
3. Click different date range buttons (7D/14D/28D)
4. Check console for AJAX requests to `ajax/doer_dashboard_data.php`
5. Verify stats update correctly in the dashboard
6. Test custom date range picker

## Expected Behavior

### Stats Scaling
- **7D:** Base values (multiplier = 1)
- **14D:** ~2x base values (multiplier = 2)
- **28D:** ~4x base values (multiplier = 4)
- **Custom:** Based on number of days / 7

### RQC Score
- Should vary slightly by range to simulate real behavior
- 7D: 85.5%
- 14D: 87.2%
- 28D: 89.1%
- Custom: 86.8%

### WND Percentage
- Always negative (represents incomplete work)
- Calculated as: `-((Pending + Delayed) / Total) * 100`
- Example: If 8 out of 20 tasks are incomplete, WND = -40%

### WND On-Time Percentage
- Always negative (represents late completions)
- Calculated as: `-(Delayed Completed / Completed) * 100`
- Example: If 3 out of 15 completed tasks were late, WND On-Time = -20%

## Troubleshooting

### Stats Not Updating
- Check browser console for JavaScript errors
- Verify AJAX request is being sent
- Check network tab for response from `ajax/doer_dashboard_data.php`

### Wrong Calculations
- Verify date range is being passed correctly in URL
- Check that `date_range` parameter is being converted to `date_from` and `date_to`
- Ensure `calculatePersonalStats()` function is using the date range

### Custom Date Range Not Working
- Verify both dates are selected
- Check that From date <= To date
- Ensure date format is YYYY-MM-DD

## Code Changes Made

1. **Fixed RQC Score Display**
   - Updated `updateStats()` to accept `rqcScore` parameter separately
   - RQC score is at `data.rqc_score`, not `data.stats.rqc_score`

2. **Date Range Conversion**
   - Added logic to convert `date_range` preset (7d/14d/28d) to actual dates
   - Custom ranges use explicit `date_from` and `date_to` parameters

3. **Initial Load**
   - Dashboard now loads with 7D range by default
   - Overview title is set correctly on page load

4. **Test File**
   - Created comprehensive test file with dummy data
   - Includes validation tests and expected behavior checks

## Notes

- The test file uses dummy data for demonstration
- Real dashboard uses actual database queries via `calculatePersonalStats()`
- All calculations follow the exact formulas specified in the requirements
- Tasks with status "can't be done" are excluded from all calculations

