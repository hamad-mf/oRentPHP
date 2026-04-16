# Bugfix Requirements Document

## Introduction

The Accounts & Ledger page (`accounts/index.php`) displays a Monthly/All-time toggle that controls the visibility of period-based metrics (Income, Expenses, Net, Overall Total). However, the Cash and Credit account balances incorrectly change when toggling between Monthly and All-time views, while Bank accounts correctly maintain their running balance regardless of the toggle state.

This inconsistency creates confusion as account balances should always represent the current running total (all-time balance), not period-filtered values. The toggle should only affect period-based metrics like Income, Expenses, Net, and Overall Total.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the user toggles from Monthly to All-time view THEN the Cash account balance changes from monthly period balance to all-time balance

1.2 WHEN the user toggles from All-time to Monthly view THEN the Cash account balance changes from all-time balance to monthly period balance

1.3 WHEN the user toggles from Monthly to All-time view THEN the Credit account balance changes from monthly period balance to all-time balance

1.4 WHEN the user toggles from All-time to Monthly view THEN the Credit account balance changes from all-time balance to monthly period balance

### Expected Behavior (Correct)

2.1 WHEN the user toggles between Monthly and All-time views THEN the Cash account balance SHALL always display the all-time running balance (cashBalance) and SHALL NOT change

2.2 WHEN the user toggles between Monthly and All-time views THEN the Credit account balance SHALL always display the all-time running balance (creditBalance) and SHALL NOT change

2.3 WHEN the user toggles between Monthly and All-time views THEN the Bank account balances SHALL CONTINUE TO display their running balance (as they currently do correctly)

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the user toggles between Monthly and All-time views THEN the Income metric SHALL CONTINUE TO toggle between monthly period income (mTotalIncome) and all-time income (totalIncome)

3.2 WHEN the user toggles between Monthly and All-time views THEN the Expenses metric SHALL CONTINUE TO toggle between monthly period expenses (mTotalExpense) and all-time expenses (totalExpense)

3.3 WHEN the user toggles between Monthly and All-time views THEN the Net metric SHALL CONTINUE TO toggle between monthly period net (mNetBalance) and all-time net (netBalance)

3.4 WHEN the user toggles between Monthly and All-time views THEN the Overall Total metric SHALL CONTINUE TO toggle between monthly overall total (mOverallTotal) and all-time overall total (overallTotal)

3.5 WHEN the user toggles between Monthly and All-time views THEN the Accounts Total metric SHALL CONTINUE TO display the running balance (accountsTotal) without changing

3.6 WHEN the user toggles between Monthly and All-time views THEN the period label SHALL CONTINUE TO show/hide appropriately (visible in Monthly, hidden in All-time)

3.7 WHEN the user toggles between Monthly and All-time views THEN the ledger table date filters SHALL CONTINUE TO update and reload the page with the appropriate date range
