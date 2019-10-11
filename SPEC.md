# CCU PLUS Course Import Package Spec

Provide artisan commands for core module to import course data to database from National Chung Cheng University.

## Spec Version

0.0.2 (2019/10/11)

## Command Spec

### course:import

import course data to database, showing imported courses number when command executed successfully

#### command arguments

|  field   |  type  | required | example |                   remark                    |
| :------: | :----: | :------: | :-----: | :-----------------------------------------: |
| semester | string |    ✓     |  1071   | semesters that will be imported to database |

#### command options

|  option   |                            remark                            |
| :-------: | :----------------------------------------------------------: |
|  --force  | import course data even if the semester was already imported |
| --dry-run | output course data which will be imported instead of importing it |

#### exceptions

- `UnexpectedValueException` - invalid semester value or semester is not exist
- `RuntimeException` - network error or target website is not available
