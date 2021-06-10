<?php


namespace Mutoco\Mplus\Import\Step;


use Exception;
use Intervention\Image\ImageManager;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Serialize\SerializableTrait;
use Psr\Http\Message\StreamInterface;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

class ImportAttachmentStep implements StepInterface
{
    use SerializableTrait;

    protected string $module;
    protected string $id;

    public function __construct(string $module, string $id)
    {
        $this->module = $module;
        $this->id = $id;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultQueue(): string
    {
        // TODO: Implement getDefaultQueue() method.
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
        // TODO: Implement activate() method.
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        $config = $engine->getConfig()->getModuleConfig($this->module);

        if (!isset($config['attachment']) || !isset($config['modelClass'])) {
            return false;
        }

        if ($stream = $engine->getApi()->loadAttachment($this->module, $this->id)) {
            $target = DataObject::get($config['modelClass'])->find('MplusID', $this->id);
            if ($target && $target->hasField($config['attachment'])) {

            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
        // TODO: Implement deactivate() method.
    }

    protected function createImage(StreamInterface $stream): Image
    {
        $manager = new ImageManager();
        $img = $manager->make($stream);
        

    }
}
