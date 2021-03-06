<?php

/*
 * Copyright (c) 2014 Palo Alto Networks, Inc. <info@paloaltonetworks.com>
 * Author: Christophe Painchaud <cpainchaud _AT_ paloaltonetworks.com>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.

 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
*/
class ServiceGroup
{
	use PathableName;
	use ReferencableObject;
	use XmlConvertible;

    /**
     * @var Service|ServiceGroup[]
     */
	public $members = Array();


	/**
	 * @var null|ServiceStore
	 */
	public $owner = null;

	
	
	public function ServiceGroup($name,$owner=null)
	{
		$this->owner = $owner;
		$this->name = $name;
	}


	/**
	 * returns number of members in this group
	 * @return int
	 */
	public function count()
	{
		return count($this->members);
	}


	public function load_from_domxml($xml)
	{
		$this->xmlroot = $xml;
		
		$this->name = DH::findAttribute('name', $xml);
		if( $this->name === FALSE )
			derr("name not found\n");

		if( $this->owner->owner->version >= 60 )
		{
			$membersRoot = DH::findFirstElement('members', $this->xmlroot);

			if( $membersRoot === false )
			{
				derr('unsupported non v6 syntax type ServiceGroup', $this->xmlroot);
			}

			foreach( $membersRoot->childNodes as $node)
			{
				if( $node->nodeType != 1 ) continue;

                $memberName = $node->textContent;

                if( strlen($memberName) < 1 )
                    derr('found a member with empty name !', $node);

				$f = $this->owner->findOrCreate($memberName, $this, true);
				$this->members[] = $f;
			}

		}
		else
		{
			foreach( $xml->childNodes as $node)
			{
				if( $node->nodeType != 1 ) continue;

                $memberName = $node->textContent;

                if( strlen($memberName) < 1 )
                    derr('found a member with empty name !', $node);

				$f = $this->owner->findOrCreate($memberName, $this, true);
				$this->members[] = $f;

			}
		}
	
	}
	

 
	public function setName($newname)
	{
		$this->xmlroot['attributes']['name'] = $newname;
		$this->setRefName($newname);
		
	}

	/**
	 * @param Service|ServiceGroup $newObject
	 * @param bool $rewriteXml
	 * @return bool
	 */
	public function add($newObject, $rewriteXml = true)
	{
		
		if( !is_object($newObject) )
			derr("Only objects can be passed to this function");
		
		if( ! in_array($newObject, $this->members, true) )
		{
			$this->members[] = $newObject;
			$newObject->addReference($this);
			if( $rewriteXml )
			{
				if( $this->owner->owner->version >= 60 )
				{
					$membersRoot = DH::findFirstElement('members', $this->xmlroot);
					if( $membersRoot === false )
					{
						derr('<members> not found');
					}

					$tmpElement = $membersRoot->appendChild($this->xmlroot->ownerDocument->createElement('member'));
					$tmpElement->nodeValue = $newObject->name();
				}
				else
				{
					$tmpElement = $this->xmlroot->appendChild($this->xmlroot->ownerDocument->createElement('member'));
					$tmpElement->nodeValue = $newObject->name();
				}
			}
			
			return true;
		}
		
		return false;
	}

	/**
	 * @param Service|ServiceGroup $old
	 * @param bool $rewritexml
	 * @return bool
	 */
	public function remove( $old , $rewritexml = true)
	{
		if( !is_object($old) )
			derr("Only objects can be passed to this function");
		
		
		$found = false;
		$pos = array_search($old, $this->members, TRUE);
		
		if( $pos === FALSE )
			return false;
		else
		{
			$found = true;
			unset($this->members[$pos]);
			$old->removeReference($this);
			if($rewritexml)
				$this->rewriteXML();
		}
		
		
		return $found;
	}

	public function &getXPath()
	{
		$str = $this->owner->getServiceGroupStoreXPath()."/entry[@name='".$this->name."']";

		return $str;
	}
	
	public function replaceReferencedObject($old, $new)
	{
		if( is_null($old) )
			derr("\$old cannot be null");
		

		if( in_array($old, $this->members, true) !== FALSE )
		{
			if( !is_null($new) )
			{
				$this->add($new, false);
				if( $old->name() == $new->name() )
					$this->remove($old, false);
				else
					$this->remove($old);
			}
			else
				$this->remove($old);
			
			return true;
		}
				
		return false;
	}
	
	
	public function rewriteXML()
	{
        if( $this->owner->owner->version >= 60 )
        {
            $membersRoot = DH::findFirstElement('members', $this->xmlroot);
            if( $membersRoot === false )
            {
                derr('<members> not found');
            }
            DH::Hosts_to_xmlDom($membersRoot, $this->members, 'member', false);
        }
        else
            DH::Hosts_to_xmlDom($this->xmlroot, $this->members, 'member', false);
	}
	
	/**
	*
	*
	*/
	public function referencedObjectRenamed($h)
	{
		//derr("****  SG referencedObjectRenamed was called  ****\n");
		if( in_array($h, $this->members, true) )
			$this->rewriteXML();
	}

	public function isService()
	{
		return false;
	}

	public function isGroup()
	{
		return true;
	}

	public function isTmpSrv()
	{
		return false;
	}


	/**
	 * @param Service|ServiceGroup $otherObject
	 * @return bool true if objects have same same and values
	 */
	public function equals( $otherObject )
	{
		if( ! $otherObject->isGroup() )
			return false;

		if( $otherObject->name != $this->name )
			return false;

		return $this->sameValue( $otherObject);
	}

	/**
	 * @param ServiceGroup $otherObject
	 * @return bool true if both objects contain same objects names
	 */
	public function sameValue( ServiceGroup $otherObject)
	{
		if( $this->isTmpSrv() && !$otherObject->isTmpSrv() )
			return false;

		if( $otherObject->isTmpSrv() && !$this->isTmpSrv() )
			return false;

		if( $otherObject->count() != $this->count() )
			return false;

		$lO = Array();
		$oO = Array();

		foreach($this->members as $a)
		{
			$lO[] = $a->name();
		}
		sort($lO);

		foreach($otherObject->members as $a)
		{
			$oO[] = $a->name();
		}
		sort($oO);

		$diff = array_diff($oO, $lO);

		if( count($diff) != 0 )
			return false;

		$diff = array_diff($lO, $oO);

		if( count($diff) != 0 )
			return false;

		return true;
	}

    public function & getValueDiff( ServiceGroup $otherObject)
    {
        $result = Array('minus' => Array(), 'plus' => Array() );

        $localObjects = $this->members;
        $otherObjects = $otherObject->members;


        usort($localObjects, '__CmpObjName');
        usort($otherObjects, '__CmpObjName');

        $diff = array_udiff($otherObjects, $localObjects, '__CmpObjName');

        if( count($diff) != 0 )
            foreach($diff as $d )
            {
                $result['minus'][] = $d;
            }

        $diff = array_udiff($localObjects, $otherObjects, '__CmpObjName');
        if( count($diff) != 0 )
            foreach($diff as $d )
            {
                $result['plus'][] = $d;
            }

        return $result;
    }


	public function displayValueDiff( ServiceGroup $otherObject, $indent=0, $toString = false)
	{
		$retString = '';

		$indent = str_pad(' ', $indent);

		if( !$toString )
			print $indent."Diff for between ".$this->toString()." vs ".$otherObject->toString()."\n";
		else
			$retString .= $indent."Diff for between ".$this->toString()." vs ".$otherObject->toString()."\n";

		$lO = Array();
		$oO = Array();

		foreach($this->members as $a)
		{
			$lO[] = $a->name();
		}
		sort($lO);

		foreach($otherObject->members as $a)
		{
			$oO[] = $a->name();
		}
		sort($oO);

		$diff = array_diff($oO, $lO);

		if( count($diff) != 0 )
		{
			foreach($diff as $d )
			{
				if( !$toString )
					print $indent." - $d\n";
				else
					$retString .= $indent." - $d\n";
			}
		}

		$diff = array_diff($lO, $oO);
		if( count($diff) != 0 )
			foreach($diff as $d )
			{
				if( !$toString )
					print $indent." + $d\n";
				else
					$retString .= $indent." + $d\n";
			}

		if( $toString )
			return $retString;
	}


    public function dstPortMapping()
    {
        $mapping = new ServiceDstPortMapping();

        foreach( $this->members as $member)
        {
            $localMapping = $member->dstPortMapping();
            $mapping->mergeWithMapping($localMapping);
        }

        return $mapping;
    }


	public function xml_convert_to_v6()
	{
        $newElement = $this->xmlroot->ownerDocument->createElement('members');
        $nodes = Array();

        foreach($this->xmlroot->childNodes as $node)
        {
            if( $node->nodeType != 1 )
                continue;

            $nodes[] = $node;
        }


        foreach($nodes as $node)
        {
            $newElement->appendChild($node);
        }


        $this->xmlroot->appendChild($newElement);

        return;
	}


	/**
	* @return Array list of all member objects, if some of them are groups, they are exploded and their members inserted
	*/
	public function & expand($keepGroupsInList=false)
	{
		$ret = Array();

		foreach( $this->members as  $object )
		{
			if( $object->isGroup() )
			{
				$ret = array_merge( $ret, $object->expand() );
                if( $keepGroupsInList )
                    $ret[] = $object;
			}
			else
				$ret[] = $object;
		}

		$ret = array_unique_no_cast($ret);

		return $ret;
	}

	public function members()
	{
		return $this->members;
	}


	public function API_delete()
	{
		$connector = findConnectorOrDie($this);
		$xpath = $this->getXPath();

		$connector->sendDeleteRequest($xpath);
	}

	/**
	 * @param Service|ServiceGroup $object
	 * @return bool
	 */
	public function hasObjectRecursive($object)
	{
		if( $object === null )
			derr('cannot work with null objects');

		foreach( $this->members as $o )
		{
			if( $o === $object )
				return true;
			if( $o->isGroup() )
				if( $o->hasObjectRecursive($object) ) return true;
		}

		return false;
	}
	
	
}


