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
    'Plugin |Плагин BetterBonemealDispenser',
    'https://tauzer.github.io/BetterBonemealDispenser.html',
    'Plugin for improving the dispenser | Плагин  для улучшения диспенсера',
    'https://sun9-21.userapi.com/PgLKRV3aGrF8jLteN0wLnl9pIWKr670Pyf4ifA/gGLhpJHWF34.jpg'),

    new Post(
    'Plugin |Плагин ArabCom',
    'https://tauzer.github.io/ArabCom.html',
    'Plugin for improving the dispenser | Плагин  для улучшения диспенсера',
    'https://sun9-34.userapi.com/PqGThuCRIK_jsbTt6aMToes48WlCHNLNEf8-1g/iA7eiPPuRgU.jpg'),

    new Post(
    'Plugin |Плагин Classement Faction',
    'https://tauzer.github.io/ClassementFaction.html',
    'Classement Faction plugin | Плагин  фракции',
    'https://imgur.com/ASaqNap.png'),

    new Post(
    'Plugin |Плагин AuthMe',
    'https://tauzer.github.io/AuthMe.html',
    'Registration plugin | Плагин регистрации',
    'https://sun9-9.userapi.com/n5SNN1EJqr-4EiS0jDwCao7duWzDEzMlL2RlTw/XiV2GlHTw8w.jpg')] },



  computed: {
    filteredList() {
      return this.postList.filter(post => {
        return post.title.toLowerCase().includes(this.search.toLowerCase());
      });
    } } });