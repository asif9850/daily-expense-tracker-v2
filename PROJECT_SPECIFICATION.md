# Daily Expense Tracker V2
## Project Specification

**Version:** 2.0.0  
**Status:** Final Release  
**Project Type:** Personal Expense Tracking Web Application  
**Backend:** PHP  
**Database:** MySQL  
**Frontend:** HTML5, CSS3, Vanilla JavaScript  

---

# 1. Project Overview

Daily Expense Tracker V2 is a web application designed to help users manage and monitor their personal finances.

The application allows users to:

- Register and log in securely.
- Record daily expenses.
- Edit and delete expenses.
- Manage upcoming expenses.
- Manage recurring expenses.
- Set monthly budgets.
- Receive budget alerts.
- Receive upcoming and recurring expense reminders.
- View notifications.
- Analyze spending data.
- Manage profile and password.
- Configure application preferences.
- Switch between light and dark themes.

---

# 2. Project Objectives

The main objectives of the application are:

1. Provide a simple system for recording personal expenses.
2. Help users track their monthly spending.
3. Allow users to plan upcoming financial obligations.
4. Automate management of recurring expenses.
5. Allow users to define monthly budgets.
6. Notify users when spending reaches important budget thresholds.
7. Provide reminders for upcoming, due-today, and overdue expenses.
8. Provide analytics to understand spending patterns.
9. Maintain separation between different users' data.
10. Provide a responsive interface for desktop and mobile devices.

---

# 3. User Roles

The current application uses a single user role.

## 3.1 Registered User

A registered user can:

- Create an account.
- Log in and log out.
- Manage personal expenses.
- Manage upcoming expenses.
- Manage recurring expenses.
- Manage budgets.
- View analytics.
- View notifications.
- Manage profile information.
- Change password.
- Configure preferences.
- Change application theme.

Users can access only their own financial data.

---

# 4. Authentication

## 4.1 Registration

The user can create an account through the registration page.

Required account information is stored in the `users` table.

## 4.2 Login

A registered user can log in using their account credentials.

After successful authentication, the user is redirected to the dashboard.

## 4.3 Logout

The logout operation terminates the active session and prevents further access to authenticated pages until the user logs in again.

## 4.4 Authentication Protection

Authenticated pages require a valid user session.

User-owned records must be accessed using the authenticated user's ID.

---

# 5. Dashboard

The dashboard is the main page after login.

The dashboard provides a financial overview of the user's data.

It includes information related to:

- Current expenses.
- Recent activity.
- Active recurring commitments.
- Upcoming bills.
- Budget health.
- Budget alerts.
- Quick actions.

The dashboard acts as the main navigation point for the application.

---

# 6. Expense Management

## 6.1 Add Expense

The user can create a new expense.

An expense contains information such as:

- Amount
- Category
- Date
- Description

The expense is stored in the `expenses` table.

## 6.2 View Expenses

The expenses section displays the user's recorded expenses.

Only expenses belonging to the authenticated user are displayed.

## 6.3 Edit Expense

The user can edit an existing expense.

The existing expense information is loaded into the edit form.

## 6.4 Delete Expense

The user can delete an existing expense.

The operation applies only to expenses owned by the authenticated user.

---

# 7. Upcoming Expenses

Upcoming Expenses are one-time financial obligations that are expected in the future.

Examples include:

- Bills
- Invoices
- Planned payments
- Other future one-time expenses

## 7.1 Add Upcoming Expense

The user can create an upcoming expense with its relevant amount, category, date, and description.

## 7.2 Edit Upcoming Expense

The user can modify an existing upcoming expense.

## 7.3 Delete Upcoming Expense

The user can delete an upcoming expense.

## 7.4 Mark as Paid

An upcoming expense can be marked as paid.

When an upcoming expense is marked as paid:

- The expense is treated as completed.
- Active reminders related to the item are dismissed.
- New reminders are not generated for the paid item.

---

# 8. Recurring Expenses

Recurring Expenses represent expenses that repeat according to a defined schedule.

Supported recurrence types include:

- Daily
- Weekly
- Monthly
- Yearly

## 8.1 Add Recurring Expense

The user can create a recurring expense rule.

The rule contains the required information for determining future expense cycles.

## 8.2 Edit Recurring Expense

The user can modify an existing recurring rule.

## 8.3 Delete Recurring Expense

The user can delete an existing recurring rule.

## 8.4 Pause Recurring Expense

A recurring rule can be paused.

When paused:

- Active reminders are dismissed.
- New reminders are not generated.
- The recurring rule remains stored.

## 8.5 Resume Recurring Expense

A paused recurring rule can be resumed.

The existing `next_date` is preserved when the recurring rule is resumed.

## 8.6 Generate Expense

A recurring rule can generate an actual expense.

After generation:

- The generated expense is stored as a recurring-generated expense.
- The recurring rule's `next_date` is advanced.
- Cycle-related notifications are dismissed.
- Duplicate generation for the same cycle is prevented.

---

# 9. Budget Management

Users can create and manage monthly budgets.

Budget functionality includes:

- Create budget
- View budget
- Edit budget
- Delete budget
- Monitor spending against budget

The system compares the user's expenses with the active monthly budget.

---

# 10. Budget Alerts

The application provides budget notifications based on spending thresholds.

## 10.1 80% Warning

When spending reaches or crosses 80% of the active budget, a warning notification can be generated.

## 10.2 100% Limit

When spending reaches or crosses 100% of the active budget, a budget-limit notification can be generated.

## 10.3 Budget Exceeded

When spending exceeds 100% of the budget, an exceeded-budget notification is generated with the relevant overage information.

## 10.4 Duplicate Prevention

The notification system prevents duplicate budget alerts for the same threshold and budget cycle.

---

# 11. Upcoming and Recurring Reminders

The application provides reminders for unpaid upcoming and recurring expenses.

Reminder timing depends on the user's configured reminder lead window.

Supported lead windows include:

- 1 day
- 2 days
- 3 days

Reminder states include:

1. Advance reminder
2. Due today
3. Overdue

## Reminder Rules

- Advance reminders are generated within the configured lead window.
- Due-today reminders are generated on the exact due date.
- Overdue reminders are generated for unpaid past-due items.
- Older reminder states are superseded when the item reaches a later state.
- Paid upcoming expenses do not generate new reminders.
- Paused recurring rules do not generate new reminders.
- Resumed recurring rules can generate reminders again.

Duplicate reminders are prevented.

---

# 12. Notification Center

The Notification Center provides a central location for application notifications.

Notification functionality includes:

- View notifications.
- View unread count.
- Mark individual notification as read.
- Mark notifications as read.
- Dismiss individual notifications.
- Dismiss notifications.
- Filter notifications.

Notification categories include:

- Upcoming
- Recurring
- Budget

Notifications are isolated by user.

One user must not be able to access another user's notifications.

---

# 13. Analytics

The Analytics section provides information about the user's spending.

Analytics can include:

- Monthly spending breakdown.
- Category-based spending information.
- Spending summaries.
- Visual charts.

Analytics data is based on the authenticated user's expenses.

---

# 14. Profile Management

The Profile section allows the user to manage account-related information.

Available functionality includes:

- View profile.
- Edit profile information.
- Change password.
- Manage preferences.
- Manage theme settings.

---

# 15. Preferences

Users can configure application preferences.

Preferences include reminder-related settings and theme preferences.

Preferences are stored per user.

Changing one user's preferences must not affect another user's preferences.

---

# 16. Theme System

The application supports:

- Light theme
- Dark theme

Theme state is synchronized using:

- Database preference
- PHP session
- Local storage
- Cookies

The application renders the selected theme during page loading to minimize flash of incorrect theme.

---

# 17. Navigation

The application provides navigation between major modules.

Main application areas include:

- Dashboard
- Expenses
- Upcoming
- Recurring
- Budget
- Analytics
- Notifications
- Profile

Authenticated users can navigate between these areas without losing their session.

---

# 18. Security Requirements

The application must maintain the following security requirements:

1. Authentication protection for private pages.
2. CSRF protection for POST mutations.
3. Prepared SQL statements.
4. User ownership checks.
5. No cross-user access.
6. No raw database errors displayed to users.
7. Secure session handling.
8. Sensitive credentials must not be committed to GitHub.

Every user-owned database operation must verify ownership using the authenticated user's ID.

---

# 19. Database

The application uses MySQL.

Core tables include:

- `users`
- `expenses`
- `upcoming_expenses`
- `recurring_expenses`
- `budgets`
- `user_preferences`
- `notifications`

The database schema is maintained in:

```text
database/schema.sql
```

The schema uses `CREATE TABLE IF NOT EXISTS`.

Future structural database changes must use separate SQL migrations instead of relying on `IF NOT EXISTS`.

---

# 20. UI/UX Requirements

The application should maintain:

- Clean interface.
- Minimal unnecessary scrolling.
- Responsive layouts.
- Consistent buttons.
- Consistent form controls.
- Clear success and error feedback.
- Light and dark theme support.
- Mobile-friendly navigation.
- Touch-friendly controls.

UI changes must not break existing functionality.

---

# 21. Responsive Design

The application must work across:

- Desktop
- Laptop
- Tablet
- Mobile

Responsive behavior must be tested at different screen widths.

Important areas include:

- Dashboard cards
- Navigation
- Tables
- Forms
- Analytics
- Notification Center
- Expense management screens

---

# 22. Error Handling

The application should provide clear user-facing feedback for failed operations.

Database errors must not expose raw SQL errors or stack traces to users.

Internal SQL errors may be logged for debugging and maintenance.

---

# 23. Testing Requirements

Before a release, the following areas must be tested:

- Authentication
- Expenses CRUD
- Upcoming CRUD
- Upcoming reminders
- Recurring CRUD
- Recurring reminders
- Recurring generation
- Budget CRUD
- Budget alerts
- Notifications
- Analytics
- Profile
- Preferences
- Theme switching
- User isolation
- Database schema import
- Mobile responsiveness

Existing V2.0 testing status is documented in `README.md`.

---

# 24. Code Polishing

Before every major release, the project should go through a code polishing phase.

The purpose is to:

- Remove dead code.
- Remove duplicate code.
- Remove unnecessary selectors.
- Remove temporary/debug code.
- Improve code organization.
- Keep existing functionality unchanged.

Code polishing must not introduce unnecessary feature changes.

---

# 25. Git and Version Control

The `main` branch represents the stable project state.

The current final release is:

```text
v2.0.0
```

Normal development workflow:

```bash
git status
git add .
git commit -m "Describe the change"
git push
```

Release versions should use Git tags.

Example:

```bash
git tag -a v2.0.0 -m "Daily Expense Tracker V2.0 Final Release"
git push origin v2.0.0
```

---

# 26. Current V2.0.0 Scope

The V2.0.0 release includes:

- Authentication
- Dashboard
- Expense management
- Upcoming expenses
- Recurring expenses
- Budget management
- Budget alerts
- Notifications
- Analytics
- Profile management
- Preferences
- Light/Dark theme
- Responsive UI
- Security protections
- Database schema
- Code polishing
- Testing and regression verification

---

# 27. Change Management Rule

Before adding or modifying a feature:

1. Define the requirement.
2. Identify affected module(s).
3. Identify affected file(s).
4. Define expected behavior.
5. Make the code change.
6. Test the change.
7. Perform code polishing.
8. Run regression tests.
9. Commit the change.
10. Push the change to GitHub.

Existing functionality must not be removed or changed unintentionally.

---

# 28. Future Development

Future features must be documented and approved in the project planning documentation before implementation.

A future feature must define:

- Feature purpose.
- User interaction.
- UI behavior.
- Database impact.
- Backend logic.
- Security impact.
- Testing requirements.

Features should not be added only because they seem useful during development.

---

# 29. Release Status

**Current Release:** V2.0.0  
**Status:** Final Release  
**Git Branch:** `main`  
**Git Tag:** `v2.0.0`  

The V2.0.0 release represents the current stable baseline of the Daily Expense Tracker project.
