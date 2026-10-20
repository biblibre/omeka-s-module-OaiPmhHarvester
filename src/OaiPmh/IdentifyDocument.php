<?php

namespace OaiPmhHarvester\OaiPmh;

class IdentifyDocument extends OaiDocument
{
    public function getRepositoryName(): ?string
    {
        $xpath = $this->getDOMXPath();
        $repositoryNameNodeList = $xpath->query('//oai:Identify/oai:repositoryName');
        if ($repositoryNameNodeList->count() === 0) {
            return null;
        }

        return $repositoryNameNodeList->item(0)->textContent;
    }
}
