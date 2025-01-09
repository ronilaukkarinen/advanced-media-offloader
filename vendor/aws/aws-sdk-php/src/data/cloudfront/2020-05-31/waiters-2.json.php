<?php

namespace WPFitter;

// This file was auto-generated from sdk-root/src/data/cloudfront/2020-05-31/waiters-2.json
return ['version' => 2, 'waiters' => ['DistributionDeployed' => ['description' => 'Wait until a distribution is deployed.', 'delay' => 60, 'maxAttempts' => 35, 'operation' => 'GetDistribution', 'acceptors' => [['matcher' => 'path', 'argument' => 'Distribution.Status', 'state' => 'success', 'expected' => 'Deployed']]], 'InvalidationCompleted' => ['description' => 'Wait until an invalidation has completed.', 'delay' => 20, 'maxAttempts' => 30, 'operation' => 'GetInvalidation', 'acceptors' => [['matcher' => 'path', 'argument' => 'Invalidation.Status', 'state' => 'success', 'expected' => 'Completed']]], 'StreamingDistributionDeployed' => ['description' => 'Wait until a streaming distribution is deployed.', 'delay' => 60, 'maxAttempts' => 25, 'operation' => 'GetStreamingDistribution', 'acceptors' => [['matcher' => 'path', 'argument' => 'StreamingDistribution.Status', 'state' => 'success', 'expected' => 'Deployed']]]]];
