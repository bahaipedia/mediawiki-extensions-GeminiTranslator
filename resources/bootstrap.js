window.ext = window.ext || {};
window.ext.geminiTranslator = window.ext.geminiTranslator || {};
console.log('Gemini: Loaded v2 bootstrap.js');
( function ( mw, $ ) {

    // -----------------------------------------------------------
    // 1. Define the Custom Dialog Class
    // -----------------------------------------------------------
    function GeminiDialog( config ) {
        GeminiDialog.super.call( this, config );
    }
    OO.inheritClass( GeminiDialog, OO.ui.ProcessDialog );

    // Static configuration
    GeminiDialog.static.name = 'geminiDialog';
    GeminiDialog.static.title = 'Translate Page';
    GeminiDialog.static.actions = [
        { action: 'translate', label: 'Translate', flags: 'primary' },
        { action: 'cancel', label: 'Cancel', flags: 'safe' }
    ];

    // Initialize content
    GeminiDialog.prototype.initialize = function () {
        GeminiDialog.super.prototype.initialize.call( this );

        this.panel = new OO.ui.PanelLayout( { padded: true, expanded: false } );

        this.langInput = new OO.ui.TextInputWidget( { 
            placeholder: 'es', 
            value: 'es' 
        } );

        this.panel.$element.append( 
            $( '<p>' ).text( 'Enter target language code (e.g. es, fr, de):' ),
            this.langInput.$element 
        );

        this.$body.append( this.panel.$element );
    };

    // Handle the "Translate" button click
    GeminiDialog.prototype.getActionProcess = function ( action ) {
        if ( action === 'translate' ) {
            // Emit an event so the main controller knows to start
            this.emit( 'translate', this.langInput.getValue() );
            // Close the window
            return new OO.ui.Process( function () {
                this.close();
            }, this );
        }
        // Default behavior for cancel/close
        return GeminiDialog.super.prototype.getActionProcess.call( this, action );
    };

    // -----------------------------------------------------------
    // 2. Define the Controller
    // -----------------------------------------------------------
    function GeminiTranslator() {
        this.revision = mw.config.get( 'wgRevisionId' );
        this.$content = $( '#mw-content-text > .mw-parser-output' );
        
        // Setup Window Manager
        this.windowManager = new OO.ui.WindowManager();
        $( 'body' ).append( this.windowManager.$element );

        // Instantiate our custom dialog
        this.dialog = new GeminiDialog();
        this.windowManager.addWindows( [ this.dialog ] );

        // Connect the dialog's 'translate' event to our logic
        this.dialog.connect( this, { translate: 'startTranslation' } );
    }

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

    // -----------------------------------------------------------
    // 3. Initialize
    // -----------------------------------------------------------
    $( function () {
        // Instantiate the controller once
        var translator = new GeminiTranslator();
        console.log('Gemini: Translator initialized');

        // Event Delegation for the button
        $( 'body' ).on( 'click', '#ca-gemini-translate', function( e ) {
            e.preventDefault();
            translator.open();
        } );
    } );

}( mediaWiki, jQuery ) );
