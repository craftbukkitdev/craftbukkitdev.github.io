class Post {
  constructor(title, link, author, img) {
    this.title = title;
    this.link = link;
    this.author = author;
    this.img = img;
  }}


const app = new Vue({
  el: '#app',
  data: {
    search: '',
    postList: [
    new Post(
    'Vue.js',
    'https://vuejs.org/',
    'Chris',
    'https://media.forgecdn.net/attachments/130/402/slp.bdev.png'),

    new Post(
    'React.js',
    'https://facebook.github.io/react/',
    'Tim',
    'https://media.forgecdn.net/attachments/130/402/slp.bdev.png'),

    new Post(
    'Angular.js',
    'https://angularjs.org/',
    'Sam',
    'https://media.forgecdn.net/attachments/130/402/slp.bdev.png'),

    new Post(
    'Ember.js',
    'http://emberjs.com/',
    'Rachel',
    'https://media.forgecdn.net/attachments/130/402/slp.bdev.png'),

    new Post(
    'Meteor.js',
    'https://www.meteor.com/',
    'Chris',
    'https://media.forgecdn.net/attachments/130/402/slp.bdev.png'),

    new Post(
    'Aurelia',
    'http://aurelia.io/',
    'Tim',
    'https://media.forgecdn.net/attachments/130/402/slp.bdev.png'),

    new Post(
    'Node.js',
    'https://nodejs.org/en/',
    'A. A. Ron',
    'https://media.forgecdn.net/attachments/130/402/slp.bdev.png'),

    new Post(
    'Pusher',
    'https://pusher.com/',
    'Alex',
    'https://media.forgecdn.net/attachments/130/402/slp.bdev.png'),

    new Post(
    'Feathers.js',
    'http://feathersjs.com/',
    'Chuck',
    'https://media.forgecdn.net/attachments/130/402/slp.bdev.png')] },



  computed: {
    filteredList() {
      return this.postList.filter(post => {
        return post.title.toLowerCase().includes(this.search.toLowerCase()) || post.author.toLowerCase().includes(this.search.toLowerCase());
      });
    } } });
