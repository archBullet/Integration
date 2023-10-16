<?php


class InputData
{
    private array $data;
    private array $complex;
    private string $leadType = 'В РАБОТЕ';
    private string $applicationSource = 'Агентство недвижимости';

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->complex = $this->parseComplexString($data['Parameters']['classifier']);
    }

    public function getComplexName()
    {
        return (string) $this->complex['complexName'];
    }

    public function getObjectType()
    {
        return (string) $this->complex['objectType'];
    }

    public function getClientName()
    {
        return (string) $this->data['Parameters']['fullname'];
    }

    public function getClientPhone()
    {
        return $this->data['Parameters']['mobilephone'];
    }

    public function getEntityId()
    {
        return (string) $this->data['EntityId'];
    }

    public function getEntityIdFromTask()
    {
        return (string) $this->data['Parameters']['leadid'];
    }

    public function getLeadType()
    {
        return (string) $this->leadType;
    }

    public function getApplicationSource()
    {
        return (string) $this->applicationSource;
    }

    public function getAgencyName()
    {
        return (string) $this->data['Parameters']['agencyname'];
    }

    public function getEventType()
    {
        $entityType = $this->data['EntityType'];
        if ($entityType == 'lead' && $this->data['Parameters']['communicationtype'] == 'fix') {
            return 'fix';
        }
        
        if ($entityType == 'task' && $this->data['Parameters']['subcategory'] == 'extended') {
            return 'extended';
        }
    }

    public function getAgentName()
    {
        return (string) $this->data['Parameters']['agentname'];
    }

    public function getAgentPhone()
    {
        return $this->data['Parameters']['agentphone'];
    }

    public function getMessage()
    {
        return $this->data['Parameters']['description'];
    }

    public function getPipelineId()
    {
        if ($this->complex['complexName'] == 'Новые вешки') {
            return 870771; //новые вешки
        }

        if ($this->complex['objectType'] == 'Коммерческая недвижимость') {
            return 1182237; //Коммерция
        }

        return 222958; //воронка
    }

    private function parseComplexString($complexString)
    {
        $pos = strpos($complexString, '(');
        if ($pos !== false) {
            $complexName = trim(substr($complexString, 0, $pos));
            $objectType = trim(substr($complexString, $pos + 1, -1));
            if ($objectType == 'Коммерческое помещение') {
                $objectType = 'Коммерческая недвижимость'; // в списке амо так записан
            }
        } else {
            $complexName = $complexString;
            $objectType = 'Квартира';
        }
        return ['complexName' => $complexName, 'objectType' => $objectType];
    }
}