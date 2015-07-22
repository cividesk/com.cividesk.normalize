{*
 +--------------------------------------------------------------------------+
 | Copyright IT Bliss LLC (c) 2012-2013                                     |
 +--------------------------------------------------------------------------+
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program.  If not, see <http://www.gnu.org/licenses/>.    |
 +--------------------------------------------------------------------------+
*}

{* this template is used for setting-up the Cividesk Normalize extension *}
<div class="form-item">
  <fieldset>
    <legend>{ts}Setup{/ts}</legend>
    <div class="crm-block crm-form-block crm-cividesk-normalize-form-block">
      <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top"}</div>
      <table class="form-layout-compressed">
        <tr class="crm-cividesk-normalize-form-block">
          <td class="label">Contact</td>
          <td>{$form.contact_FullFirst.html} {$form.contact_FullFirst.label}</td>
        </tr>
        <tr class="crm-cividesk-normalize-form-block">
          <td class="label">&nbsp;</td>
          <td>{$form.contact_OrgCaps.html} {$form.contact_OrgCaps.label}</td>
        </tr>
      </table>
      <table class="form-layout-compressed">
        {if $default_country}
          <tr class="crm-cividesk-normalize-form-block">
            <td class="label">Phone</td>
            <td>{$form.phone_normalize.html} {$form.phone_normalize.label}</td>
          </tr>
        {else}
          <tr class="crm-cividesk-normalize-form-block">
            <td class="label">Phone</td>
            <td>Please configure your default country in
              <a href="{crmURL p='civicrm/admin/setting/localization' q='reset=1'}">localization settings</a>
              first.
            </td>
          </tr>
        {/if}
        <tr class="crm-cividesk-normalize-form-block">
          <td class="label">&nbsp;</td>
          <td>{$form.phone_IntlPrefix.html} {$form.phone_IntlPrefix.label}</td>
        </tr>
      </table>
      <table class="form-layout-compressed">
        <tr class="crm-cividesk-normalize-form-block">
          <td class="label">Address</td>
          <td>{$form.address_StreetCaps.html}</td>
        </tr>
        <tr class="crm-cividesk-normalize-form-block">
          <td class="label">&nbsp;</td>
          <td>{$form.address_CityCaps.html}</td>
        </tr>        
        <tr class="crm-cividesk-normalize-form-block">
          <td class="label">&nbsp;</td>
          <td>{$form.address_Zip.html} {$form.address_Zip.label}</td>
        </tr>
      </table>
      <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
    </div>
  </fieldset>
  <fieldset>
    <legend>{ts}Apply{/ts}</legend>
    <div style="display:none;" class="crm-block crm-content-block crm-discount-view-form-block">
      <table class="form-layout-compressed">
        <tr class="crm-cividesk-normalize-form-block">
          <td class="label">{ts}Contacts{/ts}</td>
          <td>{ts}From:{/ts} #{$form.contact_from.html}{ts}To:{/ts}
            #{$form.contact_to.html}{$form.contact_apply.html}</td>
        </tr>
      </table>
    </div>

    {* Normalize processing form*}
    <div>
      <div class="form-item">
        <table class="form-layout-compressed">
          <tbody>
          <tr class="crm-cividesk-normalize-form-block">
            <td class="label">
              {$form.from_contact_id.label}
            </td>
            <td>{$form.from_contact_id.html}</td>
          </tr>
          <tr class="crm-cividesk-normalize-form-block">
            <td class="label">
              {$form.to_contact_id.label}
            </td>
            <td>{$form.to_contact_id.html}</td>
          </tr>
          <tr class="crm-cividesk-normalize-form-block">
              <td class="label">
                  {$form.batch_size.label}
              </td>
              <td>{$form.batch_size.html}</td>
          </tr>
          {if $smarty.get.state eq 'done'}
          <tr class="crm-cividesk-normalize-form-block">
              <td colspan="2">
              <div class="help">
                  {ts}Normalization completed with result counts as:{/ts}<br/>
                  <table class="form-layout-compressed bold">
                      <tr><td>{ts}Contact{/ts}:</td><td>{$stats.contact}</td></tr>
                      <tr><td>{ts}Phone{/ts}:</td><td>{$stats.phone}</td></tr>
                      <tr><td>{ts}Address{/ts}:</td><td>{$stats.address}</td></tr>
                  </table>
              </div>
              </td>
          </tr>
          {/if}
          <tr class="crm-cividesk-normalize-form-block">
            <td></td>
            <td class="label">
              <div class="form-item" style="text-align:left">
                <input type="hidden" value="{$qfKey}" name="qfKey">
                <input type="submit" class="form-submit" value="Perform Normalization" name="_qf_Normalize_submit">
              </div>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
  </fieldset>
</div>

<style type="text/css">
  {literal}
  #crm-container .crm-error {
    padding: 0;
  }
  {/literal}
</style>
