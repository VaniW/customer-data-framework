<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace CustomerManagementFrameworkBundle\ActionTrigger\Action;

use CustomerManagementFrameworkBundle\ActionTrigger\Event\SegmentTracked;
use CustomerManagementFrameworkBundle\ActionTrigger\RuleEnvironmentInterface;
use CustomerManagementFrameworkBundle\Model\CustomerInterface;
use CustomerManagementFrameworkBundle\Model\CustomerSegmentInterface;
use CustomerManagementFrameworkBundle\SegmentManager\SegmentManagerInterface;

class AddTrackedSegment extends AbstractAction
{
    const OPTION_REMOVE_OTHER_SEGMENTS_FROM_SEGMENT_GROUP = 'removeOtherSegmentsFromGroup';

    protected $name = 'AddTrackedSegment';

    public function process(
        ActionDefinitionInterface $actionDefinition,
        CustomerInterface $customer,
        RuleEnvironmentInterface $environment
    )
    {
        $segmentManager = \Pimcore::getContainer()->get('cmf.segment_manager');

        $trackedSegment = $environment->get(SegmentTracked::STORAGE_KEY);
        if (null === $trackedSegment) {
            return;
        }

        $segment = $segmentManager->getSegmentById($trackedSegment['id'] ?? null);
        if (!$segment instanceof CustomerSegmentInterface) {
            return;
        }

        $this->addSegment($segmentManager, $actionDefinition, $customer, $segment);
    }

    protected function addSegment(
        SegmentManagerInterface $segmentManager,
        ActionDefinitionInterface $actionDefinition,
        CustomerInterface $customer,
        CustomerSegmentInterface $segment
    )
    {
        $options = $actionDefinition->getOptions();

        $this->logger->info(
            sprintf(
                $this->name . ' action: add segment %s (%s) to customer %s (%s)',
                (string)$segment,
                $segment->getId(),
                (string)$customer,
                $customer->getId()
            )
        );

        $deleteSegments = [];

        if ($options{self::OPTION_REMOVE_OTHER_SEGMENTS_FROM_SEGMENT_GROUP} && ($segmentGroup = $segment->getGroup())) {
            $deleteSegments = $segmentManager->getSegmentsFromSegmentGroup(
                $segmentGroup,
                [$segment]
            );
        }

        $segmentManager->mergeSegments(
            $customer,
            [$segment],
            $deleteSegments,
            $this->name . ' action trigger action'
        );

        $segmentManager->saveMergedSegments($customer);
    }
}
