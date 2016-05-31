<?php

namespace Elixir\DI;

use Elixir\Dispatcher\Event;

/**
 * @author Cédric Tanghe <ced.tanghe@gmail.com>
 */
class ContainerEvent extends Event 
{
    /**
     * @var string
     */
    const BINDED = 'binded';
    
    /**
     * @var string
     */
    const RESOLVED = 'resolved';
    
    /**
     * @var string
     */
    const TAGGED = 'tagged';
    
    /**
     * @var string
     */
    const ALIASED = 'aliased';
    
    /**
     * @var string 
     */
    protected $service;
    
    /**
     * @var string 
     */
    protected $tag;
    
    /**
     * @var string 
     */
    protected $alias;
    
    /**
     * {@inheritdoc}
     * @param array $params
     */
    public function __construct($type, array $params = [])
    {
        parent::__construct($type);
        
        $params += [
            'service' => null,
            'tag' => null,
            'alias' => null
        ];
        
        $this->service = $params['service'];
        $this->tag = $params['tag'];
        $this->alias = $params['alias'];
    }

    /**
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }
    
    /**
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }
    
    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }
}
