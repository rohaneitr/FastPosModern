<?php

namespace App\Modules\Core\Traits;

use App\Modules\Tenant\Models\Activity;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Auditable Trait — Phase 9: Enterprise Audit Trail
 *
 * A drop-in replacement for directly using Spatie's LogsActivity trait.
 * Composing this trait on a model gives you:
 *
 *   1. Automatic logging of all fillable attribute changes (create/update/delete)
 *   2. logOnlyDirty() — only the changed fields are stored, not the full model
 *   3. PII Masking — sensitive fields are replaced with '********' BEFORE the
 *      log entry is written to the database (write-time defence)
 *   4. Standard log name derived from the model's class name (e.g. 'App.Sale')
 *      for easy filtering in the AuditLogController
 *
 * ── USAGE ──────────────────────────────────────────────────────────────────
 *
 *   class Product extends Model
 *   {
 *       use BelongsToBusiness, Auditable;
 *       // No getActivitylogOptions() needed — defaults are enterprise-grade.
 *       // Override if you need model-specific exclusions:
 *       //   protected array $auditExclude = ['cost_price', 'internal_note'];
 *   }
 *
 * ── PII MASKING MECHANISM ──────────────────────────────────────────────────
 *
 * Spatie fires a `causedBy()->withProperties()->log()` call chain internally.
 * We hook into `tapActivity()` — a Spatie extension point called with the
 * fully-built Activity model just before it is saved. At this point we walk
 * the `properties` collection and replace any matching sensitive field values
 * with '********'. The Activity model's `getPropertiesAttribute()` accessor
 * provides a second read-time masking pass for defence-in-depth.
 *
 * ── LOG NAMING CONVENTION ──────────────────────────────────────────────────
 *
 * Log names follow the pattern: module.ModelClass
 *   - Product  → 'inventory.Product'
 *   - Sale     → 'sales.Sale'
 *   - User     → 'iam.User'
 *
 * This allows the AuditLogController to filter by log_name for module-scoped views.
 *
 * @version Phase 9 — Enterprise Audit Trail
 */
trait Auditable
{
    use LogsActivity;

    /**
     * Model-level exclusions. Override in the consuming model to add
     * model-specific fields that should NEVER appear in the audit log,
     * even in masked form. These fields are completely omitted.
     *
     * Example: protected array $auditExclude = ['internal_cost'];
     */
    // protected array $auditExclude = [];

    /**
     * Returns the Spatie LogOptions configuration for this model.
     *
     * This method SHOULD NOT be overridden on individual models — use
     * $auditExclude for exclusions. The unified defaults ensure all models
     * behave consistently for compliance purposes.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $exclude = array_merge(
            Activity::MASKED_FIELDS, // Always exclude raw PII from being stored at all
            $this->auditExclude ?? []
        );

        return LogOptions::defaults()
            ->logFillable()           // Log all $fillable fields (not guarded internals)
            ->logOnlyDirty()          // Only record what actually changed — keeps logs lean
            ->dontSubmitEmptyLogs()   // Don't create a log entry if nothing changed
            ->logExcept($exclude);    // Hard-exclude PII — belt AND suspenders approach
    }

    /**
     * Spatie extension point: called with the Activity model just before save.
     *
     * This is the PRIMARY write-time PII masking layer. We walk the properties
     * collection and replace any sensitive field values with '********'.
     *
     * Unlike logExcept() (which drops the key entirely), masking preserves the
     * key name in the log so auditors know the field was changed, but cannot
     * read the new value. This satisfies GDPR Article 5(1)(f) and PCI-DSS 3.x.
     *
     * @param  \Spatie\Activitylog\Contracts\Activity $activity
     * @param  string                                  $eventName  'created'|'updated'|'deleted'
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName): void
    {
        $properties = $activity->properties;

        if (! $properties instanceof \Illuminate\Support\Collection) {
            return;
        }

        $maskedFields = Activity::MASKED_FIELDS;

        foreach (['attributes', 'old'] as $section) {
            if ($properties->has($section)) {
                $data = $properties->get($section);
                if (is_array($data)) {
                    $masked = false;
                    foreach ($maskedFields as $field) {
                        if (array_key_exists($field, $data)) {
                            $data[$field] = '********';
                            $masked       = true;
                        }
                    }
                    if ($masked) {
                        $properties->put($section, $data);
                    }
                }
            }
        }

        $activity->properties = $properties;
    }

    /**
     * The log name used for this model's activities.
     * Defaults to 'module.ClassName' derived from the namespace.
     * Override if you need a custom log name.
     */
    public function getLogNameToUse(): string
    {
        // Extract: App\Modules\Inventory\Models\Product → 'inventory.Product'
        $parts     = explode('\\', static::class);
        $className = end($parts);
        // Find the module segment (index 2 if namespace is App\Modules\{Module}\...)
        $module    = isset($parts[2]) ? strtolower($parts[2]) : 'system';

        return "{$module}.{$className}";
    }
}
