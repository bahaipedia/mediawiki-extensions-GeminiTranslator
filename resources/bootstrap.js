window.ext = window.ext || {};
window.ext.geminiTranslator = window.ext.geminiTranslator || {};
console.log('Gemini: Loaded bootstrap.js');
( function ( mw, $ ) {

    function GeminiTranslator() {
        this.revision = mw.config.get( 'wgRevisionId' );
        this.$content = $( '#mw-content-text > .mw-parser-output' );
        this.setupDialog();
    }

    GeminiTranslator.prototype.setupDialog = function () {
        this.langInput = new OO.ui.TextInputWidget( { 
            placeholder: 'es', 
            value: 'es' 
        } );

        this.dialog = new OO.ui.ProcessDialog( {
            size: 'medium'
        } );
        
        this.dialog.title.setLabel( 'Translate Page' );
        this.dialog.actions.set( [
            { action: 'translate', label: 'Translate', flags: 'primary' },
            { action: 'cancel', label: 'Cancel', flags: 'safe' }
        ] );

        this.dialog.initialize();
        this.dialog.$body.append( 
            $( '<p>' ).text( 'Enter target language code (e.g. es, fr, de):' ),
            this.langInput.$element 
        );

        this.dialog.getProcess = ( action ) => {
            if ( action === 'translate' ) {
                this.startTranslation( this.langInput.getValue() );
                return new OO.ui.Process( () => { this.dialog.close(); } );
            }
            return new OO.ui.Process( () => { this.dialog.close(); } );
        };

        var windowManager = new OO.ui.WindowManager();
        $( 'body' ).append( windowManager.$element );
        windowManager.addWindows( [ this.dialog ] );
        this.windowManager = windowManager;
    };

    GeminiTranslator.prototype.open = function () {
        this.windowManager.openWindow( this.dialog );
    };

    GeminiTranslator.prototype.startTranslation = function ( targetLang ) {
        this.$content.css( 'opacity', '0.5' );
        mw.notify( 'Starting translation...' );

        this.fetchSection( 0, targetLang ).done( ( data ) => {
            this.$content.html( data.html );
            this.$content.css( 'opacity', '1' );
            
            this.$restOfPage = $( '<div>' ).attr('id', 'gemini-translated-body').appendTo( this.$content );

            this.fetchNextSection( 1, targetLang );

        } ).fail( ( err ) => {
            console.error( err );
            mw.notify( 'Translation failed.', { type: 'error' } );
            this.$content.css( 'opacity', '1' );
        } );
    };

    GeminiTranslator.prototype.fetchNextSection = function ( sectionId, targetLang ) {
        var $loader = $( '<div>' ).text( 'Loading section ' + sectionId + '...' ).appendTo( this.$restOfPage );

        this.fetchSection( sectionId, targetLang ).done( ( data ) => {
            $loader.remove();
            
            if ( data.html && data.html.trim() !== '' ) {
                $( '<div>' ).html( data.html ).appendTo( this.$restOfPage );
                this.fetchNextSection( sectionId + 1, targetLang );
            } else {
                mw.notify( 'Translation complete!' );
            }
        } ).fail( () => {
            $loader.remove();
        } );
    };

    GeminiTranslator.prototype.fetchSection = function ( sectionId, targetLang ) {
        return $.ajax( {
            method: 'POST',
            url: mw.util.wikiScript( 'rest' ) + '/gemini-translator/translate_section/' + this.revision,
            contentType: 'application/json',
            data: JSON.stringify( {
                targetLang: targetLang,
                section: sectionId
            } )
        } );
    };

    // Initialize Singleton and Attach Event
    $( function () {
        // Create one instance of the translator
        var translator = new GeminiTranslator();

        // Listen for clicks on the indicator link
        $( 'body' ).on( 'click', '#ca-gemini-translate', function( e ) {
            e.preventDefault();
            translator.open();
        } );
    } );

}( mediaWiki, jQuery ) );
