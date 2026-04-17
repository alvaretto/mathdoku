# MathDoku activity plugin for Moodle

MathDoku (also known as KenKen) is a 9×9 numeric puzzle where each row and column must contain the digits 1–9 without repetition. Cells are grouped into **cages** marked with a thick border; each cage shows a target number and an arithmetic operation (addition, subtraction, multiplication, or division) that its cells must satisfy.

This plugin generates a unique, randomised puzzle for each student and each attempt. Students fill in the grid directly in the browser and submit for automatic grading.

---

## Features

- **Randomised puzzles** — every student/attempt combination gets a different puzzle, generated deterministically from a seed so results are reproducible.
- **Three difficulty levels:**
  - *Easy* — additions and subtractions, cages of 1–3 cells, 2–3 given (pre-filled) cells.
  - *Medium* — all four operations, cages up to 4 cells.
  - *Hard* — some operations hidden, cages up to 5 cells.
- **Auto-save** — the student's progress is saved silently every 5 seconds.
- **Configurable attempts** — teacher can set 1 to unlimited attempts per student.
- **Grade aggregation** — best, average, last, or first attempt.
- **Optional solution reveal** — teacher can choose to show the correct solution after grading (only when incorrect).
- **Gradebook integration** — grades are pushed to the Moodle gradebook automatically.
- **Privacy API** — GDPR-compliant; supports data export and deletion per user.
- **Real-time duplicate detection** — cells that violate row/column uniqueness are highlighted in red as the student types.

---

## Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.3 or later (tested up to 5.1) |
| PHP       | 8.1 or later |
| Database  | MySQL 5.7+ / MariaDB 10.4+ / PostgreSQL 13+ |

---

## Installation

### Via Moodle plugin installer (recommended)

1. Download the latest release ZIP from the [Moodle plugins directory](https://moodle.org/plugins/mod_mathdoku).
2. In Moodle, go to **Site administration → Plugins → Install plugins**.
3. Upload the ZIP and follow the on-screen instructions.

### Manual installation

1. Unzip the package.
2. Copy the `mathdoku` folder to `<moodleroot>/mod/`.
3. Log in as admin and go to **Site administration → Notifications** to trigger the database upgrade.

---

## Usage

1. In a course, turn editing on and **Add an activity → MathDoku**.
2. Set difficulty, maximum attempts, grade method, and whether to show the solution after grading.
3. Save. Students will see a new puzzle when they open the activity.

---

## Accessibility

- The grid uses `role="grid"` for screen readers.
- Keyboard navigation: arrow keys and Tab move between cells.
- Duplicate-entry cells are highlighted in red (colour + text cue via reduced saturation on error state).

---

## Privacy

This plugin stores the following personal data per user:

- Attempt number, state (in-progress / finished), and timestamps.
- The student's submitted grid values.
- The grade and correctness of each finished attempt.

The puzzle solution is stored server-side and is never sent to the browser. All data is deleted when the activity or course is deleted. Full GDPR support via Moodle's Privacy API (data export and deletion on request).

---

## License

GNU General Public License v3 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html).

Copyright © 2026 Álvaro Ángel Martínez <<alvaroangelm@iepedacitodecielo.edu.co>>
