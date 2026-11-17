<?php

use App\Models\Member;
use App\Services\MembershipGradeResolver;
use Illuminate\Support\Collection;

function gradeCollection(): Collection
{
    return collect([
        ['id' => 1, 'name' => 'Graduate'],
        ['id' => 2, 'name' => 'Associate'],
        ['id' => 3, 'name' => 'Member'],
        ['id' => 4, 'name' => 'Fellow'],
    ])->mapWithKeys(fn (array $grade): array => [
        Member::normaliseName($grade['name']) => $grade,
    ]);
}

test('it resolves plain grade names and confirmed aliases', function (string $supplied, string $expected): void {
    $resolver = new MembershipGradeResolver;

    $grade = $resolver->resolve($supplied, gradeCollection());

    expect($grade)->not->toBeNull()
        ->and($grade['name'])->toBe($expected);
})->with([
    'plain member' => ['Member', 'Member'],
    'plain associate' => ['Associate', 'Associate'],
    'plain graduate' => ['Graduate', 'Graduate'],
    'plain fellow' => ['Fellow', 'Fellow'],
    'member research' => ['member_research', 'Member'],
    'member research with space' => ['Member Research', 'Member'],
    'member associate' => ['member_associate', 'Associate'],
    'member exams' => ['member_exams', 'Associate'],
    'member exam' => ['member_exam', 'Associate'],
    'member graduate' => ['member_graduate', 'Graduate'],
    'member grad' => ['member_grad', 'Graduate'],
    'member nysc' => ['member_nysc', 'Graduate'],
    'case and spacing' => ['  MEMBER-GRAD  ', 'Graduate'],
]);

test('it rejects an unrecognised grade', function (): void {
    $resolver = new MembershipGradeResolver;

    expect($resolver->resolve('Platinum', gradeCollection()))->toBeNull();
});

test('it computes the confirmed one-step progression', function (?string $current, ?string $expected): void {
    $resolver = new MembershipGradeResolver;

    expect($resolver->nextGradeName($current))->toBe($expected);
})->with([
    'graduate' => ['Graduate', 'Associate'],
    'associate' => ['Associate', 'Member'],
    'member' => ['Member', 'Fellow'],
    'fellow' => ['Fellow', null],
    'unknown' => ['Platinum', null],
    'missing' => [null, null],
]);

test('it only accepts exactly one step up', function (string $from, string $to, bool $expected): void {
    $resolver = new MembershipGradeResolver;

    expect($resolver->isOneStepUp($from, $to))->toBe($expected);
})->with([
    'graduate to associate' => ['Graduate', 'Associate', true],
    'associate to member' => ['Associate', 'Member', true],
    'member to fellow' => ['Member', 'Fellow', true],
    'graduate to member' => ['Graduate', 'Member', false],
    'associate to fellow' => ['Associate', 'Fellow', false],
    'same grade' => ['Member', 'Member', false],
    'retrogressive' => ['Member', 'Associate', false],
]);
