<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

 use Luracast\Restler\RestException;


/**
 * API class for thirdparty object
 *
 * @smart-auto-routing false
 * @access protected 
 * @class  DolibarrApiAccess {@requires user,external}
 * 
 */
class ThirdpartyApi extends DolibarrApi
{
    /**
     *
     * @var array   $FIELDS     Mandatory fields, checked when create and update object 
     */
    static $FIELDS = array(
        'name'
    );

    /**
     * @var Societe $company {@type Societe}
     */
    public $company;

    /**
     * Constructor
     *
     * @url	thirdparty/
     * 
     */
    function __construct()
    {
		global $db, $conf;
		$this->db = $db;
        $this->company = new Societe($this->db);
        
        if (! empty($conf->global->SOCIETE_MAIL_REQUIRED)) {
            static::$FIELDS[] = 'email';
        }
    }

    /**
     * Get properties of a thirdparty object
     *
     * Return an array with thirdparty informations
     *
     * @param 	int 	$id ID of thirdparty
     * @return 	array|mixed data without useless information
	 * 
     * @url	GET thirdparty/{id}
     * @throws 	RestException
     */
    function get($id)
    {		
		if(! DolibarrApiAccess::$user->rights->societe->lire) {
			throw new RestException(401);
		}
			
        $result = $this->company->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'Thirdparty not found');
        }
		
		if( ! DolibarrApi::_checkAccessToResource('societe',$this->company->id)) {
			throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		}

		return $this->_cleanObjectDatas($this->company);
    }

    /**
     * List thirdparties
     * 
     * Get a list of thirdparties
     * 
     * @param   int     $mode       Set to 1 to show only customers 
     *                              Set to 2 to show only prospects
     *                              Set to 3 to show only those are not customer neither prospect
     * @param   string  $sortfield  Sort field
     * @param   string  $sortorder  Sort order
     * @param   int     $limit      Limit for list
     * @param   int     $page       Page number
     * @return array Array of thirdparty objects
     * 
     * @url	GET /thirdparty/list
     *
     */
    function getList($mode=0, $sortfield = "s.rowid", $sortorder = 'ASC', $limit = 0, $page = 0) {
        global $db, $conf;
        
        $obj_ret = array();
        
        $socid = DolibarrApiAccess::$user->societe_id ? DolibarrApiAccess::$user->societe_id : '';
            
        // If the internal user must only see his customers, force searching by him
        if (! DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) $search_sale = DolibarrApiAccess::$user->id;

        $sql = "SELECT s.rowid";
        if ((!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) $sql .= ", sc.fk_soc, sc.fk_user"; // We need these fields in order to filter by sale (including the case where the user can only see his prospects)
        $sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
        
        if ((!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc"; // We need this table joined to the select in order to filter by sale
        $sql.= ", ".MAIN_DB_PREFIX."c_stcomm as st";
        $sql.= " WHERE s.fk_stcomm = st.id";
        if ($mode == 1) $sql.= " AND s.client IN (1, 3)";
        if ($mode == 2) $sql.= " AND s.client IN (2, 3)";
        if ($mode == 3) $sql.= " AND s.client IN (0)";
        $sql.= ' AND s.entity IN ('.getEntity('societe', 1).')';
        if ((!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) $sql.= " AND s.rowid = sc.fk_soc";
        if ($socid) $sql.= " AND s.rowid = ".$socid;
        if ($search_sale > 0) $sql.= " AND s.rowid = sc.fk_soc";		// Join for the needed table to filter by sale
        
        // Insert sale filter
        if ($search_sale > 0)
        {
            $sql .= " AND sc.fk_user = ".$search_sale;
        }
        
        $nbtotalofrecords = 0;
        if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
        {
            $result = $db->query($sql);
            $nbtotalofrecords = $db->num_rows($result);
        }

        $sql.= $db->order($sortfield, $sortorder);

        if ($limit) {
            if ($page < 0)
            {
                $page = 0;
            }
            $offset = $limit * $page;

            $sql.= $db->plimit($limit + 1, $offset);
        }

		$result = $db->query($sql);
        if ($result)
        {
            $num = $db->num_rows($result);
            while ($i < $num)
            {
                $obj = $db->fetch_object($result);
                $soc_static = new Societe($db);
                if($soc_static->fetch($obj->rowid)) {
                    $obj_ret[] = parent::_cleanObjectDatas($soc_static);
                }
                $i++;
            }
        }
        else {
            throw new RestException(503, 'Error when retrieve thirdparties : ' . $sql);
        }
        if( ! count($obj_ret)) {
            throw new RestException(404, 'Thirdparties not found');
        }
		return $obj_ret;
    }
    
    /**
     * Show customers
     * 
     * @return array    List of customers
     * 
     * @url GET /thirdparty/list/customers
     */
    function getListCustomers() {
        return $this->getList(1);
    }
    
    /**
     * Show prospects
     * 
     * @return array    List of prospects
     * 
     * @url GET /thirdparty/list/prospects
     */
    function getListProspects() {
        return $this->getList('',1);
    }
    
     /**
     * Show other
     * 
     * @return array    List of thirpdparties who are not customer neither prospect
     * 
     * @url GET /thirdparty/list/others
     */
    function getListOthers() {
        return $this->getList('','',1);
    }
    
    /**
     * Create thirdparty object
     *
     * @param array $request_data   Request datas
     * @return int  ID of thirdparty
     * 
     * @url	POST thirdparty/
     */
    function post($request_data = NULL)
    {
        if(! DolibarrApiAccess::$user->rights->societe->creer) {
			throw new RestException(401);
		}
        // Check mandatory fields
        $result = $this->_validate($request_data);
        
        foreach($request_data as $field => $value) {
            $this->company->$field = $value;
        }
        return $this->company->create(DolibarrApiAccess::$user);
    }

    /**
     * Update thirdparty
     *
     * @param int   $id             Id of thirdparty to update
     * @param array $request_data   Datas   
     * @return int 
     * 
     * @url	PUT thirdparty/{id}
     */
    function put($id, $request_data = NULL)
    {
        if(! DolibarrApiAccess::$user->rights->societe->creer) {
			throw new RestException(401);
		}
        
        $result = $this->company->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'Thirdparty not found');
        }
		
		if( ! DolibarrApi::_checkAccessToResource('societe',$this->company->id)) {
			throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		}

        foreach($request_data as $field => $value) {
            $this->company->$field = $value;
        }
        
        if($this->company->update($id, DolibarrApiAccess::$user,1,'','','update'))
            return $this->get ($id);
        
        return false;
    }
    
    /**
     * Delete thirdparty
     *
     * @param int $id   Thirparty ID
     * @return integer
     * 
     * @url	DELETE thirdparty/{id}
     */
    function delete($id)
    {
        if(! DolibarrApiAccess::$user->rights->societe->supprimer) {
			throw new RestException(401);
		}
        $result = $this->company->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'Thirdparty not found');
        }
		
		if( ! DolibarrApi::_checkAccessToResource('societe',$this->company->id)) {
			throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		}
        
        return $this->company->delete($id);
    }
    
    /**
     * Validate fields before create or update object
     * 
     * @param array $data   Datas to validate
     * @return array
     * 
     * @throws RestException
     */
    function _validate($data)
    {
        $thirdparty = array();
        foreach (ThirdpartyApi::$FIELDS as $field) {
            if (!isset($data[$field]))
                throw new RestException(400, "$field field missing");
            $thirdparty[$field] = $data[$field];
        }
        return $thirdparty;
    }
}
