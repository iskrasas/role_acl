<?php

namespace RoleAccess\Acl\Tests\Unit;

use RoleAccess\Acl\Services\AclResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AclResolverDecisionTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function candidateCases(): array
    {
        return [
            'two segments' => ['users.index', ['users.index', 'users.*', '*']],
            'three segments' => ['admin.users.index', ['admin.users.index', 'admin.users.*', 'admin.*', '*']],
            'single segment' => ['dashboard', ['dashboard', '*']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('candidateCases')]
    public function candidates_are_most_specific_first(string $name, array $expected): void
    {
        $this->assertSame($expected, AclResolver::candidates($name));
    }

    #[Test]
    public function specific_allow_beats_wildcard_deny(): void
    {
        $rules = [
            'users.*' => ['user' => null, 'groups' => [false]],
            'users.index' => ['user' => null, 'groups' => [true]],
        ];

        $this->assertTrue(AclResolver::decide($rules, 'users.index'));
        $this->assertFalse(AclResolver::decide($rules, 'users.create'));
    }

    #[Test]
    public function user_rule_beats_group_rule_at_same_specificity(): void
    {
        $rules = ['users.*' => ['user' => true, 'groups' => [false]]];

        $this->assertTrue(AclResolver::decide($rules, 'users.index'));
    }

    #[Test]
    public function specificity_beats_user_over_group(): void
    {
        $rules = [
            'users.index' => ['user' => null, 'groups' => [true]],
            'users.*' => ['user' => false, 'groups' => []],
        ];

        $this->assertTrue(AclResolver::decide($rules, 'users.index'));
        $this->assertFalse(AclResolver::decide($rules, 'users.edit'));
    }

    #[Test]
    public function deny_wins_among_groups_at_same_specificity(): void
    {
        $rules = ['users.index' => ['user' => null, 'groups' => [true, false]]];

        $this->assertFalse(AclResolver::decide($rules, 'users.index'));
    }

    #[Test]
    public function no_matching_rule_means_deny(): void
    {
        $rules = ['posts.*' => ['user' => null, 'groups' => [true]]];

        $this->assertFalse(AclResolver::decide($rules, 'users.index'));
        $this->assertFalse(AclResolver::decide([], 'users.index'));
    }

    #[Test]
    public function global_wildcard_allows_everything_unless_overridden(): void
    {
        $rules = [
            '*' => ['user' => null, 'groups' => [true]],
            'admin.acl.*' => ['user' => null, 'groups' => [false]],
        ];

        $this->assertTrue(AclResolver::decide($rules, 'admin.users.index'));
        $this->assertFalse(AclResolver::decide($rules, 'admin.acl.groups.index'));
    }

    #[Test]
    public function prefix_wildcard_does_not_match_sibling_prefixes(): void
    {
        $rules = ['users.*' => ['user' => null, 'groups' => [true]]];

        $this->assertFalse(AclResolver::decide($rules, 'users_archive.index'));
    }
}
