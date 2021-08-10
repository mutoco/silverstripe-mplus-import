<?php

namespace Mutoco\Mplus\Extension;

use Mutoco\Mplus\Api\XmlNS;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class VocabularyItemFluentExtension extends DataExtension
{
    private static $translate = [
        'Value',
        'Language'
    ];

    public function onUpdateFromNode(TreeNode $node, ImportEngine $engine): void
    {
        if ($group = $this->owner->VocabularyGroup()) {
            $data = $engine->getApi()->queryVocabularyData($group->Name, $this->owner->MplusID);
            if (!$data) {
                Injector::inst()->get(LoggerInterface::class)->warn(sprintf(
                    'Unable to load Data for Vocabulary Group "%s", item ID %s',
                    $group->Name,
                    $this->owner->MplusID
                ));
                return;
            }

            $xml = new \DOMDocument();
            if ($xml->loadXML($data->getContents())) {
                $xpath = new \DOMXPath($xml);
                $xpath->registerNamespace('ns', XmlNS::VOCABULARY);
                foreach (Locale::getLocales() as $localeRecord) {
                    $locale = $localeRecord->getLocale();
                    $lang = locale_get_primary_language($locale);
                    $nodes = $xpath->query(sprintf('//ns:terms/ns:term[ns:isoLanguageCode="%s"]/ns:content', $lang));

                    if ($nodes && $nodes->count() > 0 && ($term = $nodes->item(0))) {
                        $value = $term->nodeValue;
                        FluentState::singleton()->withState(function(FluentState $state) use ($locale, $lang, $value) {
                            $state->setLocale($locale);
                            $this->owner->Value = $value;
                            $this->owner->Language = $lang;
                            $this->owner->write();
                        });
                    }
                }
            }
        }
    }
}
