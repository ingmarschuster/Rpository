{**
 * settingsForm.tpl
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Rpository plugin settings
 *
 * $Id$
 *}
{strip}
{assign var="pageTitle" value="plugins.generic.rpository.settings"}
{include file="common/header.tpl"}
{/strip}
{literal}
<script>
function editVis(value){
    if(value == 0){
        document.getElementById("pv1table").setAttribute("style", "display:none");
        document.getElementById("pv2table").setAttribute("style", "display:none");
    }    
    if(value == 1){
        document.getElementById("pv1table").setAttribute("style", "display:table");
        document.getElementById("pv2table").setAttribute("style", "display:none");
    }
    if(value == 2){
        document.getElementById("pv1table").setAttribute("style", "display:none");
        document.getElementById("pv2table").setAttribute("style", "display:table");
    }
    
}
</script>
{/literal}
<div id="rpositorySettings">  
    <div id="description">{translate key="plugins.generic.rpository.settings.description"}</div>
    <div class="separator"></div>
    <br/>
    <form method="post" action="{plugin_url path="settings"}">
        {include file="common/formErrors.tpl"}
        <table width="100%" class="data">
            <tr valign="top">
                <td align="right" width="10%" class="label">{translate key="plugins.generic.rpository.settings.path"}{fieldLabel name="path"}</td>
                <td width="40%" class="value">
                    <input type=text name="path" value={$path}> 
                    <br/>
                </td>
            </tr>
            <tr valign="top">
                <td align="right" width="10%" class="label">{fieldLabel name="hostname" }{translate key="plugins.generic.rpository.settings.hostname"}</td>
                <td width="40%" class="value">
                    <input type=text name="hostname" value={$hostname}> 
                    <br/>
                </td>
            </tr>
            <tr valign="top">
                <td align="right" width="10%" class="label">{fieldLabel name="documentroot" }{translate key="plugins.generic.rpository.settings.documentroot"}</td>
                <td width="40%" class="value">
                    <input type=text name="documentroot" value={$documentroot}> 
                    <br/>
                </td>
			</tr>
            <tr valign="top">
                <td align="right" width="10%" class="label">{translate key="plugins.generic.rpository.settings.pidselect"}{fieldLabel name="pidselect" }</td>
                <td align="left" width="40%" class="value">
                 
			 <select name="pidstatus" size="1" class="selectMenu" onChange='editVis(this.value)' value={$pidstatus}>
                        <option label="disabled" value="0" {if $pidstatus eq 0}  selected {/if}>PID disabled</option>
                       
                        <option label="enabled" value="2" {if $pidstatus eq 2} selected {/if}>{translate key="plugins.generic.rpository.settings.pidusev2"}</option>
                    </select>
                   
            </tr>
        </table>
        <br/>
        <table style={if $pidstatus eq 1}"display:table"{else}"display:none"{/if} width="100%" class="data" id="pv1table">
            <tr valign="top">
                <td align="right" width="10%" class="label">{translate key="plugins.generic.rpository.settings.pidv1user"}{fieldLabel name="pidv1_user" }</td>
                <td width="40%" class="value">
                    <input type=text name="pidv1_user" value={$pidv1_user}> 
                    <br/>
                </td>
            </tr>
            <tr valign="top">
                <td align="right" width="10%" class="label">{translate key="plugins.generic.rpository.settings.pidv1password"}{fieldLabel name="pidv1_pw" }</td>
                <td width="40%" class="value">
                    <input type=password name="pidv1_pw" value={$pidv1_pw}> 
                    <br/>
                </td>
            </tr>
            <tr valign="top">
                <td align="right" width="10%" class="label">{translate key="plugins.generic.rpository.settings.pidv1serviceurl"}{fieldLabel name="pidv1_service_url" }</td>
                <td width="40%" class="value">
                    <input type=text name="pidv1_service_url" value={$pidv1_service_url}> 
                    <br/>
                </td>
            </tr>
            <tr valign="top">
                <td align="right" width="10%" class="label">{translate key="plugins.generic.rpository.settings.pidv1timeout"}{fieldLabel name="pidv1_timeout" }</td>
                <td width="40%" class="value">
                    <input type=text name="pidv1_timeout" value={$pidv1_timeout}> 
                    <br/>
                </td>
            </tr>
            {if $archives_without_pidv1 neq 0}
            <tr valign="top">
                <td align="right" width="10%" class="value">
                    <input type=checkbox name="fetch_missing_pids_v1"> 
                    <br/>
                </td>
                <td align="left" idth="40%" class="label">{translate key="plugins.generic.rpository.settings.fetchpidsv1"}{fieldLabel name="missing_pids_v1" }</td>
            </tr>
            {/if}
        </table>
        <table style={if $pidstatus eq 2}"display:table"{else}"display:none"{/if} width="100%" class="data" id="pv2table">
            <tr valign="top">
                <td align="right" width="10%" class="label">{translate key="plugins.generic.rpository.settings.pidv2user"}{fieldLabel name="pidv2_user" }</td>
                <td width="40%" class="value">
                    <input type=text name="pidv2_user" value={$pidv2_user}> 
                    <br/>
                </td>
            </tr>
            <tr valign="top">
                <td align="right" width="10%" class="label">{translate key="plugins.generic.rpository.settings.pidv2password"}{fieldLabel name="pidv2_pw" }</td>
                <td width="40%" class="value">
                    <input type=password name="pidv2_pw" value={$pidv2_pw}> 
                    <br/>
                </td>
            </tr>
            <tr valign="top">
                <td align="right" width="10%" class="label">{fieldLabel name="pidv2_prefix" }{translate key="plugins.generic.rpository.settings.pidv2prefix"}</td>
                <td width="40%" class="value">
                    <input type=text name="pidv2_prefix" value={$pidv2_prefix}>
                    <br/>
                </td>
            </tr>
            <tr valign="top">
                <td align="right" width="10%" class="label">{translate key="plugins.generic.rpository.settings.pidv2serviceurl"}{fieldLabel name="pidv2_service_url" }</td>
                <td width="40%" class="value">
                    <input type=text name="pidv2_service_url" value={$pidv2_service_url}> 
                    <br/>
                </td>
            </tr>
            <tr valign="top">
                <td align="right" idth="10%" class="label">{translate key="plugins.generic.rpository.settings.pidv2timeout"}{fieldLabel name="pidv2_timeout" }</td>
                <td width="40%" class="value">
                    <input type=text name="pidv2_timeout" value={$pidv2_timeout}> 
                    <br/>
                </td>
            </tr>
            {if $archives_without_pidv2 neq 0}<tr valign="top">
                <td align="right" width="10%" class="value" >
                    <input type=checkbox name="fetch_missing_pids_v2"> 
                    <br/>
                </td>
                <td align="left" idth="40%" class="label">{translate key="plugins.generic.rpository.settings.fetchpidsv2"}{fieldLabel name="missing_pids_v2" }</td>
            </tr>{/if}
        </table>
        <br>
        <input type="submit" name="save" class="button defaultButton" value="{translate key="common.save"}"/><input type="button" class="button" value="{translate key="common.cancel"}" onclick="history.go(-1)"/>
    </form>
</div>
{include file="common/footer.tpl"}


