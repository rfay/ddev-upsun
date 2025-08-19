# Claude Code Instructions

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Communication Style

- Use direct, concise language without unnecessary adjectives or adverbs
- Avoid flowery or marketing-style language ("tremendous", "dramatically", "revolutionary", etc.)
- Don't include flattery or excessive praise ("excellent!", "perfect!", "great job!")
- State facts and findings directly without embellishment
- Skip introductory phrases like "I'm excited to", "I'd be happy to", "Let me dive into"
- Avoid concluding with summary statements unless specifically requested
- When presenting options or analysis, lead with the core information, not commentary about it

### Testing

**Automated Tests:**
- `bats tests` - Run all add-on tests
- `bats tests/test-basic.bats` - Test basic functionality
- `bats tests/test-pull.bats` - Test pull command integration

**Manual Testing:**
- Create test project: `cd ~/tmp/ddev-test && ddev config --project-type=php`
- Install local add-on: `ddev get ~/workspace/ddev-upsun`
- Test with sample `.upsun` configuration directory
- Verify `ddev pull upsun` functionality

**Test Projects:**
- Keep sample Upsun configurations in `tests/fixtures/`
- Test various PHP versions (8.0, 8.1, 8.2, 8.3)
- Test database types (MySQL, MariaDB, PostgreSQL)

### Whitespace and Formatting

- **Never add trailing whitespace** - Blank lines must be completely empty (no spaces or tabs)
- Match existing indentation style exactly (spaces vs tabs, indentation depth)
- Preserve the file's existing line ending style
- Run linting tools to catch whitespace issues before committing

## Working with Claude Code

### Branch Naming

Use descriptive branch names that include:

- Date in YYYYMMDD format
- Your GitHub username
- Brief description of the work

Format: `YYYYMMDD_<username>_<short_description>`

Examples:

- `20250719_rfay_vite_docs`
- `20250719_username_fix_networking`
- `20250719_contributor_update_tests`

**Branch Creation Strategy:**

The recommended approach for creating branches is:

```bash
git fetch upstream && git checkout -b <branch_name> upstream/main --no-track
```

This method:

- Fetches latest upstream changes
- Creates branch directly from upstream/main
- Doesn't require syncing local main branch
- Uses --no-track to avoid tracking upstream/main

### Pull Request Creation

When creating pull requests for DDEV, follow the PR template structure from `.github/PULL_REQUEST_TEMPLATE.md`:

**Required Sections:**

- **The Issue:** Reference issue number with `#<issue>` and brief description
- **How This PR Solves The Issue:** Technical explanation of the solution
- **Manual Testing Instructions:** Step-by-step guide for testing changes
- **Automated Testing Overview:** Description of tests or explanation why none needed
- **Release/Deployment Notes:** Impact assessment and deployment considerations

**For commits that will become PRs:** Include the complete PR template content in commit messages. This ensures GitHub PRs are pre-populated and don't require additional editing.

**Complete Pre-Commit Checklist:**

1. Make your changes
2. Run appropriate tests (`make test` or targeted tests)
3. Fix any issues reported
4. Stage changes with `git add`
5. Commit with proper message format

## DDEV Add-on Development

### Project Structure
- `install.yaml` - Primary add-on configuration file
- `tests/` - Bats test files for add-on functionality
- `src/` - PHP source files for configuration parsing (executed in DDEV web container)
- `docker-compose.*.yaml` - Additional service definitions (if needed)

### Development Workflow
- Test with real Upsun projects in `~/tmp/`
- Use `ddev get .` for local add-on installation during development
- Validate against DDEV add-on template requirements

### Upsun Integration

**Configuration Source:**
- `.upsun/` directory contains Upsun project configuration files
- These are INPUT files that the add-on parses to CREATE DDEV configuration
- Common files: `.upsun/config.yaml`, `.upsun/.platform.app.yaml` equivalents

**Generated DDEV Configuration:**
- `.ddev/config.yaml` updates (PHP version, project type)
- `.ddev/config.upsun.yaml` (add-on specific config)
- `.ddev/.env.upsun` (environment variables)

**Supported Translations:**
- PHP runtime versions to DDEV equivalents
- Database services (mysql, mariadb, postgresql)
- Environment variables and relationships
- Basic application configuration

**Unsupported Features (Error/Warn):**
- Multi-app configurations
- Complex service relationships beyond single database
- Workers and crons
- Advanced networking configurations

### Runtime Environment
- PHP parsing logic runs inside DDEV web container (no local PHP required)
- Upsun CLI available in DDEV web container for pull operations
- All file operations occur within DDEV context

## Important Instruction Reminders

Do what has been asked; nothing more, nothing less.
NEVER create files unless they're absolutely necessary for achieving your goal.
ALWAYS prefer editing an existing file to creating a new one.
NEVER proactively create documentation files (*.md) or README files. Only create documentation files if explicitly requested by the User.

## Task Master AI Instructions
**Import Task Master's development workflow commands and guidelines, treat as if import is in the main CLAUDE.md file.**
@./.taskmaster/CLAUDE.md
