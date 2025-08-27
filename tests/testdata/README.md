# Updating testdata from upstream repositories

The subdirectories here come from branches/environments of an actual working upsun project, https://github.com/ddev/test-upsun-addon.

New fixtures can be added with 

```bash
git subtree add --prefix=tests/testdata/<fixture> https://github.com/ddev/test-upsun-addon <fixture> --squash
```

For example,
```bash
git subtree add --prefix=tests/testdata/drupal11 https://github.com/ddev/test-upsun-addon drupal11 --squash
```

To update, use `git subtree pull`, as in:
```bash
# Fetch the upstream repo
git fetch https://github.com/ddev/test-upsun-addon

# Pull into subtree
git subtree pull --prefix=tests/testdata/drupal11 https://github.com/ddev/test-upsun-addon drupal11 --squash
```