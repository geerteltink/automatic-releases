<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\PushViaConsole;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function mkdir;
use function Safe\tempnam;
use function sys_get_temp_dir;
use function unlink;

/** @covers \Laminas\AutomaticReleases\Git\PushViaConsole */
final class PushViaConsoleTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $source;
    /** @psalm-var non-empty-string */
    private string $destination;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $source      = tempnam(sys_get_temp_dir(), 'PushViaConsoleTestSource');
        $destination = tempnam(sys_get_temp_dir(), 'PushViaConsoleTestDestination');

        Assert::notEmpty($source);
        Assert::notEmpty($destination);

        $this->source      = $source;
        $this->destination = $destination;

        unlink($this->source);
        unlink($this->destination);
        mkdir($this->source);

        // @TODO check if we need to set the git author and email here (will likely fail in CI)
        (new Process(['git', 'init'], $this->source))
            ->mustRun();
        (new Process(['git', 'config', 'user.email', 'me@example.com'], $this->source))
            ->mustRun();
        (new Process(['git', 'config', 'user.name', 'Just Me'], $this->source))
            ->mustRun();
        (new Process(['git', 'remote', 'add', 'origin', $this->destination], $this->source))
            ->mustRun();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'a commit'], $this->source))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'initial-branch'], $this->source))
            ->mustRun();
        (new Process(['git', 'clone', $this->source, $this->destination]))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'pushed-branch'], $this->source))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'ignored-branch'], $this->source))
            ->mustRun();
    }

    protected function tearDown(): void
    {
        $sourceBranches = (new Process(['git', 'branch'], $this->source))
            ->mustRun()
            ->getOutput();

        self::assertStringNotContainsString('temporary-branch', $sourceBranches);

        parent::tearDown();
    }

    public function testPushesSelectedGitRef(): void
    {
        (new PushViaConsole($this->logger))
            ->__invoke($this->source, 'pushed-branch');

        $destinationBranches = (new Process(['git', 'branch'], $this->destination))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString('pushed-branch', $destinationBranches);
        self::assertStringNotContainsString('ignored-branch', $destinationBranches);
    }

    public function testPushesSelectedGitRefAsAlias(): void
    {
        (new PushViaConsole($this->logger))
            ->__invoke($this->source, 'pushed-branch', 'pushed-alias');

        $destinationBranches = (new Process(['git', 'branch'], $this->destination))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString('pushed-alias', $destinationBranches);
        self::assertStringNotContainsString('pushed-branch', $destinationBranches);
        self::assertStringNotContainsString('ignored-branch', $destinationBranches);
    }

    public function testPushesSelectedTag(): void
    {
        (new Process(['git', 'tag', 'tag-name', '-m', 'pushed tag'], $this->source))
            ->mustRun();

        (new PushViaConsole($this->logger))
            ->__invoke($this->source, 'tag-name');

        $destinationBranches = (new Process(['git', 'tag'], $this->destination))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString('tag-name', $destinationBranches);
    }

    public function testPushesSelectedTagAsAliasBranch(): void
    {
        (new Process(['git', 'tag', 'tag-name', '-m', 'pushed tag'], $this->source))
            ->mustRun();

        (new PushViaConsole($this->logger))
            ->__invoke($this->source, 'tag-name', 'pushed-alias');

        $destinationBranches = (new Process(['git', 'branch'], $this->destination))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString('pushed-alias', $destinationBranches);
        self::assertStringNotContainsString('tag-name', $destinationBranches);
        self::assertStringNotContainsString('ignored-branch', $destinationBranches);
    }
}
