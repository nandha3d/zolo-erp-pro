<?php

namespace Tests\Support;

abstract class OperationsTestCase extends CommercialTestCase
{
    use CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOperationsFixtures();
    }
}
