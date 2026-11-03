<?php

namespace Best\Blueprint\Permissions;

/**
 * A validated permission definition: groups of abilities ("products" => ["view", "update"]) and roles
 * that grant exact names or the wildcards "*" and "<group>.*". Expanded here; spatie's wildcard mode stays off.
 */
final readonly class PermissionSet
{
    private const NAME = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * @param  list<string>  $permissions
     * @param  array<string, list<string>>  $roles
     */
    private function __construct(private array $permissions, private array $roles) {}

    public static function fromClass(string $class): self
    {
        if (! class_exists($class) || ! method_exists($class, 'groups') || ! method_exists($class, 'roles')) {
            throw new InvalidPermissionDefinition("Permission definitions class [{$class}] was not found or lacks groups()/roles().");
        }

        /** @var array<string, list<string>> $groups */
        $groups = $class::groups();
        /** @var array<string, list<string>> $roles */
        $roles = $class::roles();

        return self::from($groups, $roles);
    }

    /**
     * @param  array<string, list<string>>  $groups
     * @param  array<string, list<string>>  $roles
     */
    public static function from(array $groups, array $roles): self
    {
        $all = [];
        $byGroup = [];

        foreach ($groups as $group => $abilities) {
            self::assertName((string) $group, "group [{$group}]");

            foreach ($abilities as $ability) {
                self::assertName($ability, "ability [{$ability}] of group [{$group}]");
                $name = "{$group}.{$ability}";
                $all[$name] = true;
                $byGroup[$group][$name] = true;
            }
        }

        $superAdmin = (string) config('blueprint.super_admin', 'super-admin');
        $expanded = [];

        foreach ($roles as $role => $patterns) {
            if ($role === $superAdmin) {
                throw new InvalidPermissionDefinition("Role [{$role}] is built in (it can do everything); do not define it in roles().");
            }

            $granted = [];

            foreach ($patterns as $pattern) {
                $matched = match (true) {
                    $pattern === '*' => array_keys($all),
                    str_ends_with($pattern, '.*') => array_keys($byGroup[substr($pattern, 0, -2)] ?? []),
                    default => isset($all[$pattern]) ? [$pattern] : [],
                };

                if ($matched === []) {
                    throw new InvalidPermissionDefinition("Role [{$role}] grants [{$pattern}], which matches no permission.");
                }

                foreach ($matched as $name) {
                    $granted[$name] = true;
                }
            }

            $names = array_keys($granted);
            sort($names);
            $expanded[(string) $role] = $names;
        }

        $permissions = array_keys($all);
        sort($permissions);

        return new self($permissions, $expanded);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /** @return array<string, list<string>> role => expanded sorted permissions */
    public function roles(): array
    {
        return $this->roles;
    }

    private static function assertName(string $name, string $what): void
    {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidPermissionDefinition("Invalid {$what}: names must be lowercase letters and digits, with single dashes between words.");
        }
    }
}
