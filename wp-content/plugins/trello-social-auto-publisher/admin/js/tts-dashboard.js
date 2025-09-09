const { createElement, render, useState, useEffect } = wp.element;
const { SelectControl, Spinner } = wp.components;
const apiFetch = wp.apiFetch;

const PostList = ( { posts } ) => {
  return createElement(
    'table',
    { className: 'widefat fixed' },
    [
      createElement(
        'thead',
        {},
        createElement( 'tr', {}, [
          createElement( 'th', {}, 'Title' ),
          createElement( 'th', {}, 'Preferred Channel' ),
          createElement( 'th', {}, 'Status' ),
          createElement( 'th', {}, 'Log' ),
        ] )
      ),
      createElement(
        'tbody',
        {},
        posts.map( ( post ) => {
          const channel = post.meta && post.meta._tts_social_channel ? post.meta._tts_social_channel : [];
          const status = post.meta && post.meta._published_status ? post.meta._published_status : 'scheduled';
          const channels = Array.isArray( channel ) ? channel.join( ', ' ) : channel;
          return createElement( 'tr', { key: post.id }, [
            createElement( 'td', {}, post.title.rendered ),
            createElement( 'td', {}, channels ),
            createElement( 'td', {}, status ),
            createElement( 'td', {},
              createElement( 'a', { href: `admin.php?page=tts-social-posts&action=log&post=${ post.id }` }, 'View' )
            )
          ] );
        } )
      )
    ]
  );
};

const Dashboard = () => {
  const [ posts, setPosts ] = useState( [] );
  const [ channel, setChannel ] = useState( '' );
  const [ status, setStatus ] = useState( '' );
  const [ loading, setLoading ] = useState( true );

  useEffect( () => {
    apiFetch( { path: '/wp/v2/tts_social_post?per_page=100&_fields=id,title,meta' } )
      .then( ( data ) => {
        setPosts( data );
        setLoading( false );
      } );
  }, [] );

  const filtered = posts.filter( ( post ) => {
    const postChannel = post.meta && post.meta._tts_social_channel ? post.meta._tts_social_channel : [];
    const postStatus = post.meta && post.meta._published_status ? post.meta._published_status : 'scheduled';
    const channels = Array.isArray( postChannel ) ? postChannel : [ postChannel ];
    return ( ! channel || channels.includes( channel ) ) && ( ! status || status === postStatus );
  } );

  return createElement( 'div', {}, [
    createElement( 'div', { className: 'tts-filters' }, [
      createElement( SelectControl, {
        label: 'Channel',
        value: channel,
        options: [
          { label: 'All', value: '' },
          { label: 'Facebook', value: 'facebook' },
          { label: 'Instagram', value: 'instagram' },
          { label: 'YouTube', value: 'youtube' },
          { label: 'TikTok', value: 'tiktok' }
        ],
        onChange: ( value ) => setChannel( value )
      } ),
      createElement( SelectControl, {
        label: 'Status',
        value: status,
        options: [
          { label: 'All', value: '' },
          { label: 'scheduled', value: 'scheduled' },
          { label: 'published', value: 'published' }
        ],
        onChange: ( value ) => setStatus( value )
      } )
    ] ),
    loading ? createElement( Spinner, {} ) : createElement( PostList, { posts: filtered } )
  ] );
};

document.addEventListener( 'DOMContentLoaded', () => {
  const root = document.getElementById( 'tts-dashboard-root' );
  if ( root ) {
    render( createElement( Dashboard ), root );
  }
} );
