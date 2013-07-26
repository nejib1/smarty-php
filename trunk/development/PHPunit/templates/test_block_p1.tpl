{extends file="test_block_gp1.tpl"}
{block name=content}
some content here
{block name=section1}
{/block}
{block name=section2}
Sect2 -- {$smarty.block.child} --- 
{/block}
{/block}