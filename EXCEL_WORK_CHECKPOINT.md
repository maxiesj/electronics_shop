# Excel Work Checkpoint

Last updated: 12 August 2026

Workbook: `2026 - KENYA - BANK ACCOUNT LINKING MAIN - DROPDOWNS AND DATES.xlsx`

## Sheet relationships

- `2024 KENYA BANK LINKING` supplies records to `LINKING FORM`.
- `KENYA BANK LINKING` supplies records to `LINKING FORM 1`.
- The request ID selected in `C6` determines the customer record displayed on each form.

## Current 2024 formula work

- Column G is the generated request ID.
- IDs originally stopped at `KNLR2403000` because the T/U helper sequence ended.
- The new independent formula begins in `G3004`:

  `=IF(ISBLANK(H3004),"","KNLR"&TEXT(2400000+ROW()-3,"0"))`

- Fill this formula down as far as required, for example through `G5000`.
- The reference remains blank until a User ID is entered in column H.

## 2024 form dropdown

- Workbook-level named range: `RequestIDs2024`
- Named-range source should be extended to:

  `='2024 KENYA BANK LINKING'!$G$4:$G$5000`

- `LINKING FORM!C6` uses Data Validation type `List` with source:

  `=RequestIDs2024`

- The dropdown is now working after being configured directly in Microsoft Excel.

## Protection work

- Next/current step is protecting column G from accidental edits.
- Unlock the sheet first, lock column G, then protect the worksheet.
- Recommended formula columns to protect: A, B, F, G, T, and U.
- Keep user-entry columns unlocked.

## Other observations

- The large white area was caused by frozen panes. The suggested setup is to unfreeze panes, select `A4`, and freeze rows 1-3 only.
- `TEXTJOIN` caused `#NAME?` in some Excel environments. The direct formula above avoids `TEXTJOIN` and the T/U dependency.
- Two pre-existing broken formulas were found in `2024 KENYA BANK LINKING!A453:B453`; they were not part of the later dropdown work.

## Resume point

Confirm column G is protected while input cells remain editable. Then test:

1. Enter a new User ID in column H below row 3003.
2. Confirm column G generates the next sequential `KNLR` reference.
3. Confirm the new reference appears in the `LINKING FORM!C6` dropdown.
4. Select it and confirm the linked customer details populate correctly.
