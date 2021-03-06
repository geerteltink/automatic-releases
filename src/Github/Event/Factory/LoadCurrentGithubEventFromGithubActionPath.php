<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Event\Factory;

use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Webmozart\Assert\Assert;

use function Safe\file_get_contents;

final class LoadCurrentGithubEventFromGithubActionPath implements LoadCurrentGithubEvent
{
    private EnvironmentVariables $variables;

    public function __construct(EnvironmentVariables $variables)
    {
        $this->variables = $variables;
    }

    public function __invoke(): MilestoneClosedEvent
    {
        $path = $this->variables->githubEventPath();

        Assert::fileExists($path);

        return MilestoneClosedEvent::fromEventJson(file_get_contents($path));
    }
}
