<?php

/*
	NP_MetaEX  developed by taki (http://xxxx)
	
	USAGE
	-----
	Skin:
		<%MetaEX()%> ... item template
		mode               ... keyword / description / other
	
	EXAMPLE
	-------
	In item template or item skintype:
	(search skintype is also available)
	
	
	HISTORY
	-------
	Ver0.1 2005/08/xx

*/

class NP_MetaEX extends NucleusPlugin
{
	var $keyword_tag_format;
	var $description_tag_format;
	
	function getName()           { return 'Add Meta Tag Keyword & Description,etc for item '; }
	function getAuthor()         { return 'ZeRo'; }
	function getURL()            { return 'http://www.petit-power.com'; }
	function getVersion()        { return '0.2'; }
	function getDescription()    { return 'Allows to make a Meta Tag Keyword & Description for Item.'; }
	function supportsFeature($w) { return ($w=='SqlTablePrefix')?1:0; }
	function getEventList()      { return array('PostAddItem','PreUpdateItem',
	                                      'AddItemFormExtras','EditItemFormExtras');}
	
	// Installation
	function install()
	{
		$this->createOption("meta_keyword_format","meta keywords format", "text", '<meta name="keywords" content="<%keywords%>">');
		$this->createOption("meta_desc_format","meta description format", "text", '<meta name="description" content="<%description%>">');
		$this->createOption('del_uninstall', 'Delete a table on uninstall?', 'yesno', 'no');
		$this->createBlogOption('meta_keywords', 'Keywords (comma-separated)', 'text', '');
		
		sql_query("CREATE TABLE IF NOT EXISTS ". sql_table("plug_metaex") 
		." ( 
		itemid INT(9) NOT NULL, 
		keywords VARCHAR(255) NOT NULL DEFAULT '', 
		description VARCHAR(255) NOT NULL DEFAULT '', 
		PRIMARY KEY (itemid)
		)");
	}
	
	function uninstall()
	{
		if ($this->getOption('del_uninstall') == 'yes')
		{
			sql_query ( "DROP table IF EXISTS ". sql_table("plug_metaex") );
		}
		$this->deleteOption('meta_keyword_format');
		$this->deleteOption('meta_desc_format');
		$this->deleteBlogOption('meta_keywords');
	}
	
	function getTableList()
	{
		return array( sql_table('plug_metaex') );
	}
	
	function event_AddItemFormExtras($data)
	{
		?>
		<h3>MetaTag(EX)</h3>
		<p>
		<label for="meta_description">Description:</label>
		<input type="text" value="" id="meta_description" name="meta_description" size="60" />
		</p>
		<p>
		<label for="meta_key">Keywords:</label>
		<input type="text" value="" id="meta_key" name="meta_keywords" size="60" />
		</p>
		<?php
	}
	
	function event_EditItemFormExtras($data)
	{
		$id = intval($data['variables']['itemid']);
		$result = sql_query("SELECT itemid, keywords,description FROM ". sql_table("plug_metaex"). " WHERE itemid='$id'");
		if (sql_num_rows($result) > 0)
		{
			$keywords  = sql_result($result,0,"keywords");
			$description  = sql_result($result,0,"description");
		}
	?>
	<h3>MetaTag(EX)</h3>
	<p>
	<label for="meta_description">Description:</label>
	<input type="text" value="<?php echo htmlspecialchars($description,ENT_QUOTES) ?>" id="meta_description" name="meta_description" size="60" />
	</p>
	<p>
	<label for="meta_key">Keywords:</label>
	<input type="text" value="<?php echo htmlspecialchars($keywords,ENT_QUOTES) ?>" id="meta_key" name="meta_keywords" size="60" />
	</p>
	<?php
	}
	

	function event_PostAddItem($data)
	{
		$keywords = requestVar('meta_keywords');
		$description = requestVar('meta_description');
		
		// Nothing to do? Get out!!
		if ((!$keywords) && (!$description)) return;
		$itemid = intval($data['itemid']);
		$keywords  = sql_real_escape_string($keywords);
		$description = sql_real_escape_string($description);
		$this->insertValues($itemid, $keywords, $description);
	}
	
	function event_PreUpdateItem($data)
	{
		$keywords = requestVar('meta_keywords');
		$keywords = mb_convert_kana($keywords, "s", "UTF-8");
		$keywords  = sql_real_escape_string($keywords);
		$description = requestVar('meta_description');
		$description = sql_real_escape_string($description);
		if (empty($keywords) && empty($description))
		{	return ;}
		$itemid = intval($data['itemid']);
		
		$result = sql_query("SELECT * FROM ". sql_table("plug_metaex") ." WHERE itemid='$itemid'");
		
		if (sql_num_rows($result) > 0)
		{
			// Nothing to do? Delete it!!
			if ((!$keywords) && (!$description))
			{
				sql_query("DELETE FROM ". sql_table("plug_metaex") ." WHERE itemid='$itemid'");
				return;
			}
			else
			{
				sql_query("UPDATE ". sql_table("plug_metaex") ." SET keywords='$keywords',description='$description' WHERE itemid='$itemid'");
			}
		}
		else
		{
			// Nothing to do? Get out!!
			if (($keywords=="") && ($descritpion=="")) return;
			$this->insertValues($itemid, $keywords, $description);
		}
	}
	
	function doSkinVar($skinType,$param)
	{
		global $CONF, $manager, $blog, $itemid;
		
		$keywords = "";
		$description = "";
		$itemid = intval($itemid);
		switch ($skinType)
		{
			case 'item':
				$result = sql_query("SELECT keywords,description FROM ". sql_table("plug_metaex"). " WHERE itemid='$itemid'");
				if (sql_num_rows($result) > 0)
				{
					$keywords  = sql_result($result,0,"keywords");
					$description  = sql_result($result,0,"description");
				}
				else
				{
					$keywords = $this->otherPlugin($itemid);
					if ($keywords)
					{
						$this->insertValues($itemid, $keywords, $description);
					}
				}
				if ($keywords)
				{
					$keywords = mb_convert_kana($keywords,"s",_CHARSET);
					$ary_key = preg_split("/\s+|\[|\]|\(|\)|,/", $keywords, -1, PREG_SPLIT_NO_EMPTY);
					$keywords = join(',', $ary_key);
				}
				break;
			default:
				if (!empty($blog))
				{
					$b =& $blog;
				}
				else
				{
					$b =& $manager->getBlog($CONF['DefaultBlog']);
				}
				$blogid = $b->getID();
				$keywords = $this->getBlogOption($blogid, 'meta_keywords');
				$description = $b->getDescription();
				break;
		}
		$str = "";
		if ($param == "keywords")
		{
			if ($keywords)
			{
				$str = $this->getOption("meta_keyword_format");
				$str = $this->my_str_replace('<%keywords%>', htmlspecialchars($keywords,ENT_QUOTES),$str);
			}
		}
		if ($param == "description")
		{
			if ($description)
			{
				$str= $this->getOption("meta_desc_format");
				$str = $this->my_str_replace('<%description%>', htmlspecialchars($description,ENT_QUOTES),$str);
			}
		}
		echo $str;
	}
	function my_str_replace($search, $replace, $target, $encoding = _CHARSET)
	{
		$search_len = mb_strlen($search, $encoding);
		$replace_len = mb_strlen($replace, $encoding);
		$offset = mb_strpos($target, $search);
		while ($offset !== FALSE)
		{
			$target = mb_substr($target, 0, $offset).$replace.mb_substr($target, $offset + $search_len);
			$offset = mb_strpos($target, $search, $offset + $replace_len);
		}
		return $target;
	}
	
	function otherPlugin($itemid)
	{
	global $manager;
		if ($manager->pluginInstalled('NP_Related') || $manager->pluginInstalled('NP_RelatedEX'))
		{
			$result = sql_query("SELECT localkey FROM ". sql_table("plug_related"). " WHERE itemid='$itemid'");
			if (sql_num_rows($result) > 0)
			{
				$keywords  = sql_result($result,0,"localkey");
			}
		}
		if($manager->pluginInstalled('NP_Header'))
		{
			$result = sql_query("SELECT keywords FROM ". sql_table("plugin_meta_keywords"). " WHERE itemid='$itemid'");
			if (sql_num_rows($result) > 0)
			{
				$keywords  = sql_result($result,0,"keywords");
			}
		}
		return $keywords . $itemid;
	}
	
	function insertValues($itemid, $keywords, $description)
	{
		sql_query("INSERT INTO ". sql_table("plug_metaex") ." VALUES ('$itemid','$keywords','$description')");
	}
}
