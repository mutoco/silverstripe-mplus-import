<?php


namespace Mutoco\Mplus\Tests\Extension;


use Mutoco\Mplus\Parse\Result\TreeNode;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataExtension;

class TestSkipAttachmentExtension extends DataExtension implements TestOnly
{
    public function mplusShouldImportAttachment($field, $node)
    {
        if ($field === 'Image' && $node instanceof TreeNode) {
            return false;
        }
    }
}
