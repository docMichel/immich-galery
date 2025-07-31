// public/assets/js/modules/ImageProcessor.js

class ImageProcessor {
    constructor(imageElement) {
        this.imageElement = imageElement;
        this.maxSize = 1920;
    }

    // Convertir l'image en base64 avec redimensionnement
    async getImageAsBase64(quality = 0.9) {
        return new Promise((resolve, reject) => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();

            img.crossOrigin = 'anonymous';

            img.onload = () => {
                // Calculer les nouvelles dimensions
                const { width, height } = this._calculateDimensions(
                    img.width, 
                    img.height, 
                    this.maxSize
                );

                // Redimensionner
                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);

                // Convertir en base64
                const base64 = canvas.toDataURL('image/jpeg', quality);
                
                console.log(`📸 Image redimensionnée: ${img.width}x${img.height} → ${width}x${height}`);
                
                resolve(base64);
            };

            img.onerror = () => {
                reject(new Error('Impossible de charger l\'image'));
            };

            img.src = this.imageElement.src;
        });
    }

    // Calculer les dimensions optimales
    _calculateDimensions(originalWidth, originalHeight, maxSize) {
        let width = originalWidth;
        let height = originalHeight;

        if (width > maxSize || height > maxSize) {
            if (width > height) {
                height = Math.round((height / width) * maxSize);
                width = maxSize;
            } else {
                width = Math.round((width / height) * maxSize);
                height = maxSize;
            }
        }

        return { width, height };
    }

    // Obtenir les informations de l'image
    getImageInfo() {
        return {
            src: this.imageElement.src,
            naturalWidth: this.imageElement.naturalWidth,
            naturalHeight: this.imageElement.naturalHeight,
            displayWidth: this.imageElement.width,
            displayHeight: this.imageElement.height
        };
    }

    // Changer la taille maximale
    setMaxSize(size) {
        this.maxSize = size;
    }
}

export default ImageProcessor;