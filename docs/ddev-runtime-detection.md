# DDEV Runtime Dependency Detection - Architectural Limitation and Solution

This document outlines an architectural limitation in DDEV's runtime dependency system and proposes a solution that would benefit all DDEV add-ons.

## Current Problem

### The Limitation

DDEV's current add-on installation flow processes runtime dependencies too early in the installation sequence, before project files are available. This creates significant constraints for add-ons that need dynamic dependency detection.

### Current Installation Flow

```
1. Pre-install actions    ← Only basic PHP, no project files available
2. Runtime dependencies   ← .runtime-deps-* files processed here
3. Project files          ← Add-on PHP classes become available here  
4. Post-install actions   ← Full functionality available, but too late for dependencies
```

### Impact on ddev-upsun

This timing forces ddev-upsun to implement complex service detection logic inline in `install.yaml` rather than using proper PHP classes:

**Current Implementation (Constrained)**:
```yaml
pre_install_actions:
  - |
    <?php
    # Complex 50+ line inline service detection
    $serviceToAddon = [...];
    # Regex parsing logic
    # File creation logic
```

**Desired Implementation (Clean)**:
```yaml
pre_install_actions:
  - |
    <?php
    require_once('upsun/ServiceDetector.php');
    $detector = new \Upsun\ServiceDetector($projectRoot);
    $detector->detectAndCreateDependencies();
```

## Root Cause Analysis

### DDEV Source Code Reference

In `pkg/ddevapp/addons.go`, the `InstallAddonFromDirectory()` method shows the current flow:

```go
// 1. Pre-install actions
err = app.ProcessHooks("pre-install", installationContext)

// 2. Runtime dependencies (immediately after pre-install)
runtimeDepsFile := app.GetConfigPath(".runtime-deps-" + s.Name)
runtimeDeps, err := ParseRuntimeDependencies(runtimeDepsFile)

// 3. Project files installation
err = fileutil.CopyDir(s.Path, app.GetConfigPath(""))

// 4. Post-install actions  
err = app.ProcessHooks("post-install", installationContext)
```

### Why This Timing Exists

The current timing likely exists to:
1. Ensure dependencies are installed before the main add-on files
2. Prevent circular dependency issues
3. Keep the installation flow simple

However, this creates problems for add-ons that need sophisticated logic to determine dependencies.

## Proposed Solution

### New Installation Flow

```
1. Pre-install actions     ← Basic setup, optional dependency hints
2. Project files           ← Add-on classes and logic become available
3. Runtime dependencies    ← Dynamic detection with full add-on capabilities  
4. Post-install actions    ← Main configuration, all dependencies available
```

### Implementation Strategy

#### Option A: Additional Hook Point
Add a new hook specifically for runtime dependency detection:

```go
// 1. Pre-install actions
err = app.ProcessHooks("pre-install", installationContext)

// 2. Project files installation
err = fileutil.CopyDir(s.Path, app.GetConfigPath(""))

// 3. NEW: Runtime dependency detection hook
err = app.ProcessHooks("detect-dependencies", installationContext)

// 4. Runtime dependencies processing
runtimeDepsFile := app.GetConfigPath(".runtime-deps-" + s.Name)
runtimeDeps, err := ParseRuntimeDependencies(runtimeDepsFile)

// 5. Post-install actions
err = app.ProcessHooks("post-install", installationContext)
```

#### Option B: Move Existing Processing
Simply move the runtime dependency processing to after project file installation:

```go
// 1. Pre-install actions
err = app.ProcessHooks("pre-install", installationContext)

// 2. Project files installation  
err = fileutil.CopyDir(s.Path, app.GetConfigPath(""))

// 3. Runtime dependencies (moved here)
runtimeDepsFile := app.GetConfigPath(".runtime-deps-" + s.Name)
runtimeDeps, err := ParseRuntimeDependencies(runtimeDepsFile)

// 4. Post-install actions
err = app.ProcessHooks("post-install", installationContext)
```

## Benefits of the Solution

### For ddev-upsun

1. **Clean Architecture**: Use proper PHP classes for service detection
2. **Better Maintainability**: Separate concerns into logical modules
3. **Enhanced Testing**: Unit test service detection logic independently
4. **Easier Extension**: Add new services without complex inline code

### For All DDEV Add-ons

1. **Dynamic Dependencies**: Any add-on can implement sophisticated dependency detection
2. **Configuration-Based Logic**: Dependencies can be determined from project files
3. **Reduced Complexity**: No need for complex pre-install workarounds
4. **Better User Experience**: More intelligent dependency resolution

### Example Use Cases Enabled

1. **Framework Detection**: Auto-install framework-specific add-ons based on project files
2. **Service Discovery**: Parse various config formats (Docker Compose, Kubernetes, etc.)
3. **Conditional Dependencies**: Install add-ons based on complex project analysis
4. **Multi-Platform Support**: Handle different hosting platform configurations

## Implementation Plan

### Phase 1: Proof of Concept
1. Create a fork of DDEV with the modified installation flow
2. Test with ddev-upsun to verify the approach works
3. Ensure no regressions in existing add-on installations

### Phase 2: Community Review
1. Propose the change to the DDEV core team
2. Gather feedback from other add-on maintainers
3. Refine the implementation based on community input

### Phase 3: Implementation
1. Submit PR to DDEV core repository
2. Update documentation for add-on developers
3. Provide migration guide for existing add-ons

### Phase 4: ddev-upsun Optimization
1. Refactor service detection to use proper PHP classes
2. Improve maintainability and testing
3. Add support for more complex service configurations

## Backward Compatibility

### Ensuring Compatibility

The proposed change should maintain compatibility with existing add-ons:

1. **Pre-install Dependencies**: Add-ons that create `.runtime-deps-*` in pre-install will continue to work
2. **Simple Add-ons**: Add-ons without runtime dependencies are unaffected
3. **Static Dependencies**: Add-ons with static dependencies in `install.yaml` continue to work

### Migration Path

Existing add-ons can gradually adopt the new capability:

1. **Optional Adoption**: No immediate changes required
2. **Incremental Migration**: Move dependency detection logic as needed
3. **Enhanced Features**: Take advantage of new capabilities over time

## Technical Considerations

### Circular Dependencies

The new timing must handle circular dependencies:
- Prevent infinite loops in dependency resolution
- Detect and report circular dependency chains
- Provide clear error messages for resolution

### Performance Impact

Moving runtime dependency processing should have minimal performance impact:
- Most add-ons don't use runtime dependencies
- File operations are similar regardless of timing
- Network operations for dependency installation remain the same

### Error Handling

Enhanced error handling for the new flow:
- Clear messages when dependency detection fails
- Rollback capabilities if dependency installation fails
- Debugging information for add-on developers

## Alternative Approaches Considered

### 1. Two-Phase Installation
Require users to run add-on installation twice - not user-friendly.

### 2. Manual Dependency Installation
Install dependencies manually in post-install actions - bypasses DDEV's dependency system.

### 3. Configuration-Based Hints
Use static configuration to hint at possible dependencies - less flexible than dynamic detection.

### 4. External Dependency Scanner
Separate tool to scan and prepare dependencies - adds complexity and extra steps.

## Conclusion

Moving DDEV's runtime dependency processing to occur after project file installation would unlock significant capabilities for add-on developers while maintaining backward compatibility. This change would enable cleaner, more maintainable add-on architectures and better user experiences.

The proposed solution addresses a fundamental architectural constraint that currently forces suboptimal implementations across the DDEV add-on ecosystem.

## Implementation Resources

- **DDEV Core Repository**: https://github.com/ddev/ddev
- **Add-on Development Docs**: https://ddev.readthedocs.io/en/stable/developers/add-ons/
- **Current Implementation**: `pkg/ddevapp/addons.go` in DDEV core
- **Test Cases**: Examples from ddev-upsun service detection requirements

## References

- [DDEV Dynamic Dependencies PR #7586](https://github.com/ddev/ddev/pull/7586)
- [Upsun Available Services](https://docs.upsun.com/add-services.html#available-services)
- [DDEV Add-on Development Guide](https://ddev.readthedocs.io/en/stable/developers/add-ons/)
- [Platform.sh to Upsun Migration](https://docs.upsun.com/get-started/migrate-from-platformsh.html)