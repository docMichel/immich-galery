<!-- views/duplicate-thumbnail-template.php -->
<script type="text/template" id="duplicate-thumbnail-template">
    <div class="dup-image {{primary}}" 
         data-asset-id="{{assetId}}"
         data-group-index="{{groupIndex}}"
         data-image-index="{{imgIndex}}">
        
        <img src="../public/image-proxy.php?id={{assetId}}&type=thumbnail" 
             alt="{{filename}}"
             title="{{filename}}">
        
        <div class="image-info">
            {{#isPrimary}}
                <span class="primary-badge">⭐ Principale</span>
            {{/isPrimary}}
            
            <div class="filename" title="{{filename}}">{{filenameShort}}</div>
            
            <!-- Infos qualité -->
            {{qualityInfo}}
            
            <label class="select-primary">
                <input type="radio" 
                       name="primary-{{groupIndex}}" 
                       value="{{imgIndex}}"
                       {{checked}}
                       onchange="duplicateManager.setPrimary({{groupIndex}}, {{imgIndex}})">
                Principale
            </label>
            
            {{#hasGPS}}
                <div class="gps-info" title="GPS: {{lat}}, {{lng}}">
                    📍 GPS
                </div>
            {{/hasGPS}}
        </div>
        
        <button class="btn-remove" 
                onclick="duplicateManager.removeFromGroup({{groupIndex}}, {{imgIndex}})"
                title="Retirer du groupe">
            ❌
        </button>
    </div>
</script>