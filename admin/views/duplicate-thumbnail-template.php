<?php
// admin/views/duplicate-thumbnail-template.php
// Ce fichier génère un template JavaScript
?>
<script type="text/template" id="duplicate-thumbnail-template">
    <div class="dup-image {{primary}}" 
         data-asset-id="{{assetId}}"
         data-group-index="{{groupIndex}}"
         data-image-index="{{imgIndex}}">
        
        {{#hasGPS}}
        <span class="gps-badge" title="GPS: {{lat}}, {{lng}}">📍</span>
        {{/hasGPS}}
        
        <img src="../public/image-proxy.php?id={{assetId}}&type=thumbnail" 
             alt="{{filename}}"
             onerror="this.onerror=null; duplicateManager.reloadThumbnail(this, '{{assetId}}')"
             loading="lazy">
        
        <div class="image-info">
            {{#isPrimary}}
            <span class="primary-badge">⭐ Principale</span>
            {{/isPrimary}}
            
            <label class="select-primary">
                <input type="radio" 
                       name="primary-{{groupIndex}}" 
                       value="{{imgIndex}}"
                       {{checked}}
                       onchange="duplicateManager.setPrimary({{groupIndex}}, {{imgIndex}})">
                Principale
            </label>
            
            {{#filename}}
            <small class="filename" title="{{filename}}">{{filenameShort}}</small>
            {{/filename}}
        </div>
        
        <button class="btn-remove" 
                onclick="duplicateManager.removeFromGroup({{groupIndex}}, {{imgIndex}})"
                title="Retirer du groupe">
            ❌
        </button>
    </div>
</script>