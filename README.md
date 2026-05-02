# Mason Time Tracker

A Laravel + Vue time entry interface built as part of the Mason engineering assessment.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3, SQLite
- **Frontend:** Vue 3 (Composition API), Pinia, Vite
- **Tables:** DataTables

## Setup

```bash
git clone https://github.com/clesmalo/mason-time-tracker.git
cd mason-time-tracker

composer install
npm install

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Then in two separate terminals:

```bash
php artisan serve
npm run dev
```

Visit `http://127.0.0.1:8000`

## Features

### Core
- Create time entries per company, employee, project, task, date and hours
- Business rule enforced backend-side: an employee can only work on one project per date, but multiple tasks within that project
- Cascading dropdowns: employees, projects and tasks filter based on selected company
- History tab with all submitted entries

### UX Decisions

**Global filters as form pre-fill**
The dropdowns and date selectors at the top of the page double as a form pre-fill mechanism. 
Selecting a company pre-populates the entry form and narrows all dependent dropdowns automatically. 
Likewise, selecting an employee, task or project pre-populates those fields.
Date field is pre-populated with Start Date.
This reduces repetitive input when logging multiple entries for the same company.

**On-the-go project and task creation**
Users can create new projects and tasks directly from the form dropdowns without leaving the page, reducing friction for first-time data entry.

**Keyboard-friendly navigation**
Full tab-key navigation across all form fields. 
Enter submits the form, except when a dropdown is open, in which case Enter selects an option.
Designed for efficient data entry without mouse dependency.

**Summary totals**
Summary cards display total hours, total entries, active employees and active projects. Totals reflect the global company filter but not DataTable pagination, because pagination is a display concern, not a data concern.

**Frontend caching with Pinia**
Catalog data (companies, employees, projects, tasks) is cached in Pinia state after first load. This avoids redundant API calls when switching tabs or reusing dropdowns, keeping the interface snappy even as data grows.

### Bonuses Implemented
- ✅ Faster data entry (tab navigation, dropdown pre-fill, on-the-go creation)
- ✅ Better validation UX (field-level highlighting, inline error messages)
- ✅ Summary totals (filtered by company, independent of pagination)
- ✅ History table with pagination and filtering via DataTables

## Performance Considerations

- Catalog endpoints are cached frontend-side via Pinia to minimize API calls
- Cascading dropdowns only fetch data when a parent selection changes
- Summary totals are computed server-side to avoid loading full datasets into the frontend
- DataTables handles pagination client-side after initial load, suitable for moderate dataset sizes. For very large datasets, server-side pagination would be the next step.

## AI Usage

This project was built in collaboration with Claude (Anthropic). The AI conversation export is included in the repository as `ai-conversation.json`.

The AI was used for code generation under my direction. Architectural decisions, UX reasoning, and implementation choices were made by me and explained throughout the conversation — which I'd encourage you to read as part of evaluating this submission.